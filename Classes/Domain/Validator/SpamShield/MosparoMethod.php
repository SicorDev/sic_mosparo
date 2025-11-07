<?php
declare(strict_types=1);
namespace SICOR\SicMosparo\Domain\Validator\SpamShield;

use In2code\Powermail\Domain\Model\Field;
use In2code\Powermail\Domain\Validator\SpamShield\AbstractMethod;
use Throwable;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use function curl_close;
use function curl_exec;
use function curl_init;
use function curl_setopt;
use function hash;
use function hash_hmac;
use function http_build_query;
use function json_decode;
use function json_encode;
use function ksort;
use function str_replace;
use function str_starts_with;
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_USERPWD;
use const JSON_THROW_ON_ERROR;

class MosparoMethod extends AbstractMethod
{

    /**
     * Check if secret key is given and set it
     *
     * @return void
     * @throws \Exception
     */
    public function initialize(): void
    {
        if (empty($this->configuration['privatekey']) && $this->isFormWithMosparoField()) {
            throw new \LogicException(
                'No privatekey given. Please add a secret key to TypoScript Constants',
                1607012762
            );
        }
    }

    /**
     * @return bool true if spam recognized
     */
    public function spamCheck(): bool
    {
        if (!$this->isFormWithMosparoField() || $this->isCaptchaCheckToSkip()) {
            return false;
        }

        $formData = $GLOBALS['TYPO3_REQUEST']->getParsedBody() ?? [];
        $formData = $this->flattenArrayWithBracketKeys($formData);

        $host = $this->configuration['host'];
        $projectPrivateKey = $this->configuration['privatekey'];
        $projectPublicKey = $this->configuration['publickey'];

        return !$this->isValid($formData, $host, $projectPrivateKey, $projectPublicKey);
    }

    protected function isValid(array $formData, string $host, string $projectPrivateKey, string $projectPublicKey): bool
    {
        try {
            // 1. Remove the ignored fields from the form data
            // You have to do this only if you have ignored fields in your form

            // 2. Extract the submit and validation token from the form data
            $submitToken = $GLOBALS['TYPO3_REQUEST']->getParsedBody()['_mosparo_submitToken'] ?? '';
            $validationToken = $GLOBALS['TYPO3_REQUEST']->getParsedBody()['_mosparo_validationToken'] ?? '';

            $preparedFormData = [];
            foreach ($formData as $fieldName => $value) {
                if (str_starts_with($fieldName, '_mosparo_')) {
                    continue;
                }

                // Currently mosparo does not support brackets in field names, we also remove the powermail field prefix
                $fieldName = str_replace(['tx_powermail_pi1[field]', '[', ']'], '', $fieldName);

                $preparedFormData[$fieldName] = str_replace("\r\n", "\n", $value);
            }

            // 4. Generate the hashes
            foreach ($preparedFormData as $fieldName => $value) {
                $preparedFormData[$fieldName] = hash('sha256', $value);
            }

            ksort($preparedFormData);

            // 5. Generate the form data signature
            $jsonPreparedFormData = json_encode($preparedFormData, JSON_THROW_ON_ERROR);
            $formDataSignature = hash_hmac('sha256', $jsonPreparedFormData, $projectPrivateKey);

            // 6. Generate the validation signature
            $validationSignature = hash_hmac('sha256', $validationToken, $projectPrivateKey);

            // 7. Prepare the verification signature
            $combinedSignatures = $validationSignature . $formDataSignature;
            $verificationSignature = hash_hmac('sha256', $combinedSignatures, $projectPrivateKey);

            // 8. Collect the request data
            $apiEndpoint = '/api/v1/verification/verify'; // This is the API of mosparo, so it's a fixed value
            $requestData = [
                'submitToken' => $submitToken,
                'validationSignature' => $validationSignature,
                'formSignature' => $formDataSignature,
                'formData' => $preparedFormData,
            ];

            // 9. Generate the request signature
            $jsonRequestData = json_encode($requestData, JSON_THROW_ON_ERROR);
            $combinedApiEndpointJsonRequestData = $apiEndpoint . $jsonRequestData;
            $requestSignature = hash_hmac('sha256', $combinedApiEndpointJsonRequestData, $projectPrivateKey);

            // 10. Send the API request
            $ch = curl_init($host . $apiEndpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($requestData));
            curl_setopt($ch, CURLOPT_USERPWD, $projectPublicKey . ':' . $requestSignature);
            $response = curl_exec($ch);
            if ($response === false) {
                return false;
            }

            // 11. Check the response
            $responseData = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            curl_close($ch);

            return isset($responseData['valid'], $responseData['verificationSignature']) && $responseData['valid'] && $responseData['verificationSignature'] === $verificationSignature;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Check if current form has a mosparo field
     *
     * @return bool
     */
    protected function isFormWithMosparoField(): bool
    {
        try {
            foreach ($this->mail->getForm()?->getPages() as $page) {
                /** @var Field $field */
                foreach ($page->getFields() as $field) {
                    if ($field->getType() === 'mosparo') {
                        return true;
                    }
                }
            }
        } catch (Throwable) {}

        return false;
    }


    /**
     * Captcha check should be skipped on createAction if there was a confirmationAction where the captcha was
     * already checked before
     * Note: $this->flexForm is only available in powermail 3.9 or newer
     *
     * @return bool
     */
    protected function isCaptchaCheckToSkip(): bool
    {
        if (property_exists($this, 'flexForm')) {
            $action = $this->getActionName();
            $confirmationActive = $this->flexForm['settings']['flexform']['main']['confirmation'] === '1';
            $optinActive = $this->flexForm['settings']['flexform']['main']['optin'] === '1';

            if (($action === 'create' && $confirmationActive) || ($action === 'checkCreate' && $confirmationActive)) {
                return true;
            }

            if ($action === 'optinConfirm' && $optinActive) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string "confirmation" or "create"
     */
    protected function getActionName(): string
    {
        $request = $GLOBALS['TYPO3_REQUEST'];
        $pluginVariables = $request->getQueryParams()['tx_powermail_pi1'];
        ArrayUtility::mergeRecursiveWithOverrule($pluginVariables, $request->getParsedBody()['tx_powermail_pi1']);
        return $pluginVariables['action'];
    }

    protected function flattenArrayWithBracketKeys(array $array, string $prefix = ''): array {
        $result = [];
        foreach ($array as $key => $value) {
            $newKey = $prefix === '' ? $key : $prefix . '[' . $key . ']';
            if (is_array($value)) {
                $result += $this->flattenArrayWithBracketKeys($value, $newKey);
            } else {
                $result[$newKey] = $value;
            }
        }
        return $result;
    }
}
