<?php
/**
 * NicSRS SSL Response Formatting
 * 
 * Standardizes JSON responses for AJAX calls
 * 
 * @package    WHMCS
 * @author     HVN GROUP
 * @version    2.0.0
 */

namespace nicsrsSSL;

class nicsrsResponse
{
    /**
     * Status codes
     */
    const STATUS_SUCCESS = 1;
    const STATUS_ERROR = 0;
    const STATUS_PROCESSING = 2;
    
    /**
     * Return success response
     * 
     * @param mixed $data Response data
     * @param string $message Success message
     * @return string JSON response
     */
    public static function success($data = null, $message = 'Success')
    {
        $response = [
            'status' => self::STATUS_SUCCESS,
            'msg' => $message,
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        return self::toJson($response);
    }
    
    /**
     * Return error response
     * 
     * @param string|array $errors Error message(s)
     * @param mixed $data Additional data
     * @return string JSON response
     */
    public static function error($errors, $data = null)
    {
        if (!is_array($errors)) {
            $errors = [$errors];
        }
        
        $response = [
            'status' => self::STATUS_ERROR,
            'msg' => 'failed',
            'error' => $errors,
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        return self::toJson($response);
    }
    
    /**
     * Return API error response
     * 
     * @param string $message API error message
     * @param mixed $apiResponse Original API response
     * @return string JSON response
     */
    public static function api_error($message, $apiResponse = null)
    {
        $response = [
            'status' => self::STATUS_ERROR,
            'msg' => 'api_error',
            'error' => [$message],
        ];
        
        if ($apiResponse !== null) {
            $response['api_response'] = $apiResponse;
        }
        
        return self::toJson($response);
    }
    
    /**
     * Return processing response (for long-running operations)
     * 
     * @param string $message Status message
     * @param mixed $data Additional data
     * @return string JSON response
     */
    public static function processing($message = 'Processing', $data = null)
    {
        $response = [
            'status' => self::STATUS_PROCESSING,
            'msg' => $message,
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        return self::toJson($response);
    }
    
    /**
     * Return validation error response
     * 
     * @param array $validationErrors Array of field => error message
     * @return string JSON response
     */
    public static function validation_error(array $validationErrors)
    {
        $errors = [];
        foreach ($validationErrors as $field => $message) {
            $errors[] = "{$field}: {$message}";
        }
        
        return self::error($errors, ['validation_errors' => $validationErrors]);
    }
    
    /**
     * Return not found response
     * 
     * @param string $resource Resource name
     * @return string JSON response
     */
    public static function not_found($resource = 'Resource')
    {
        return self::error("{$resource} not found");
    }
    
    /**
     * Return unauthorized response
     * 
     * @param string $message Error message
     * @return string JSON response
     */
    public static function unauthorized($message = 'Unauthorized access')
    {
        return self::error($message);
    }
    
    /**
     * Return redirect response
     * 
     * @param string $url Redirect URL
     * @param string $message Optional message
     * @return string JSON response
     */
    public static function redirect($url, $message = '')
    {
        return self::success([
            'redirect' => true,
            'url' => $url,
        ], $message ?: 'Redirecting...');
    }
    
    /**
     * Return download response
     * 
     * @param string $url Download URL
     * @param string $filename Filename
     * @return string JSON response
     */
    public static function download($url, $filename = '')
    {
        return self::success([
            'downloadUrl' => $url,
            'filename' => $filename,
        ], 'Download ready');
    }
    
    /**
     * Return paginated response
     * 
     * @param array $items Items
     * @param int $total Total count
     * @param int $page Current page
     * @param int $perPage Items per page
     * @return string JSON response
     */
    public static function paginated(array $items, $total, $page = 1, $perPage = 20)
    {
        return self::success([
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage),
                'has_more' => ($page * $perPage) < $total,
            ],
        ]);
    }
    
    /**
     * Convert response array to JSON string
     * 
     * @param array $response Response array
     * @return string JSON string
     */
    protected static function toJson(array $response)
    {
        return json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Parse API response and return formatted response
     * 
     * @param object $apiResponse API response object
     * @param string $successMessage Success message
     * @return string JSON response
     */
    public static function fromApiResponse($apiResponse, $successMessage = 'Success')
    {
        if (!is_object($apiResponse)) {
            return self::error('Invalid API response');
        }
        
        // Check for success codes (1 = success, 2 = processing)
        if (isset($apiResponse->code)) {
            if ($apiResponse->code == 1) {
                return self::success(
                    isset($apiResponse->data) ? $apiResponse->data : null,
                    $successMessage
                );
            }
            
            if ($apiResponse->code == 2) {
                return self::processing(
                    $apiResponse->msg ?? 'Processing',
                    isset($apiResponse->data) ? $apiResponse->data : null
                );
            }
            
            // Error response
            $errorMsg = $apiResponse->msg ?? 'API Error';
            return self::api_error($errorMsg, $apiResponse);
        }
        
        return self::error('Unknown API response format');
    }
    
    /**
     * Create response from exception
     * 
     * @param \Exception $e Exception
     * @param bool $includeTrace Include stack trace (for debugging)
     * @return string JSON response
     */
    public static function fromException(\Exception $e, $includeTrace = false)
    {
        $response = [
            'status' => self::STATUS_ERROR,
            'msg' => 'exception',
            'error' => [$e->getMessage()],
        ];
        
        if ($includeTrace) {
            $response['debug'] = [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ];
        }
        
        return self::toJson($response);
    }
    
    /**
     * Send JSON response and exit
     * 
     * @param string $jsonResponse JSON response string
     * @return void
     */
    public static function send($jsonResponse)
    {
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResponse;
        exit;
    }
    
    /**
     * Send success and exit
     * 
     * @param mixed $data Response data
     * @param string $message Success message
     * @return void
     */
    public static function sendSuccess($data = null, $message = 'Success')
    {
        self::send(self::success($data, $message));
    }
    
    /**
     * Send error and exit
     * 
     * @param string|array $errors Error message(s)
     * @param mixed $data Additional data
     * @return void
     */
    public static function sendError($errors, $data = null)
    {
        self::send(self::error($errors, $data));
    }
}