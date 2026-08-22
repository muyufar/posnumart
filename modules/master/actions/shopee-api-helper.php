<?php
/**
 * Shopee API Helper Class
 * Based on official documentation: https://open.shopee.com/developer-guide/16
 * 
 * This class provides methods for:
 * - Generating signatures for different API endpoints
 * - Updating product stock
 * - Updating product prices
 * - Refreshing access tokens
 */

class ShopeeAPIHelper {
    private $partner_id;
    private $partner_key;
    private $host;
    private $access_token;
    private $shop_id;
    
    public function __construct($partner_id, $partner_key, $host, $access_token = '', $shop_id = 0) {
        $this->partner_id = $partner_id;
        $this->partner_key = $partner_key;
        $this->host = $host;
        $this->access_token = $access_token;
        $this->shop_id = $shop_id;
    }
    
    /**
     * Generate signature according to Shopee documentation
     * Base String Format: partner_id + path + timestamp + access_token + shop_id
     */
    public function generateSignature($path, $timestamp, $include_access_token = true, $include_shop_id = true) {
        $base_string = $this->partner_id . $path . $timestamp;
        
        if ($include_access_token && !empty($this->access_token)) {
            $base_string .= $this->access_token;
        }
        
        if ($include_shop_id && $this->shop_id > 0) {
            $base_string .= $this->shop_id;
        }
        
        $signature = hash_hmac('sha256', $base_string, $this->partner_key);
        
        // Debug logging
        error_log("Shopee API Signature Debug:");
        error_log("  Partner ID: " . $this->partner_id);
        error_log("  Path: " . $path);
        error_log("  Timestamp: " . $timestamp);
        error_log("  Access Token: " . ($include_access_token && !empty($this->access_token) ? 'YES' : 'NO'));
        error_log("  Shop ID: " . ($include_shop_id && $this->shop_id > 0 ? $this->shop_id : 'NO'));
        error_log("  Base String: " . $base_string);
        error_log("  Generated Signature: " . $signature);
        
        return $signature;
    }
    
    /**
     * Make API request to Shopee
     */
    private function makeRequest($path, $payload = [], $method = 'POST') {
        $timestamp = time();
        $signature = $this->generateSignature($path, $timestamp);
        
        $query_params = [
            'partner_id' => (string)$this->partner_id,
            'timestamp'  => $timestamp,
            'sign'       => $signature
        ];
        
        if (!empty($this->access_token)) {
            $query_params['access_token'] = $this->access_token;
        }
        
        if ($this->shop_id > 0) {
            $query_params['shop_id'] = $this->shop_id;
        }
        
        $url = rtrim($this->host, '/') . $path . '?' . http_build_query($query_params);
        
        error_log("Shopee API Request:");
        error_log("  URL: " . $url);
        error_log("  Method: " . $method);
        error_log("  Payload: " . json_encode($payload));
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false, // For development/testing
            CURLOPT_SSL_VERIFYHOST => false  // For development/testing
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("cURL Error: " . $error);
        }
        
        curl_close($ch);
        
        error_log("Shopee API Response:");
        error_log("  HTTP Code: " . $http_code);
        error_log("  Response: " . $response);
        
        return [
            'http_code' => $http_code,
            'response' => $response,
            'data' => json_decode($response, true)
        ];
    }
    
    /**
     * Update product stock
     * Endpoint: /api/v2/product/stock/update
     */
    public function updateStock($items) {
        $path = '/api/v2/product/stock/update';
        
        $payload = [
            'stock_list' => $items
        ];
        
        return $this->makeRequest($path, $payload);
    }
    
    /**
     * Update product price
     * Endpoint: /api/v2/product/price/update
     */
    public function updatePrice($items) {
        $path = '/api/v2/product/price/update';
        
        $payload = [
            'price_list' => $items
        ];
        
        return $this->makeRequest($path, $payload);
    }
    
    /**
     * Update both stock and price in one call
     * Endpoint: /api/v2/product/stock/update_price_quantity
     */
    public function updateStockAndPrice($items) {
        $path = '/api/v2/product/stock/update_price_quantity';
        
        $payload = [
            'item_list' => $items
        ];
        
        return $this->makeRequest($path, $payload);
    }
    
    /**
     * Refresh access token
     * Endpoint: /api/v2/auth/access_token/get
     */
    public function refreshAccessToken($refresh_token) {
        $path = '/api/v2/auth/access_token/get';
        
        $payload = [
            'refresh_token' => $refresh_token,
            'partner_id' => (int)$this->partner_id,
            'shop_id' => (int)$this->shop_id
        ];
        
        return $this->makeRequest($path, $payload);
    }
    
    /**
     * Get shop info
     * Endpoint: /api/v2/shop/get_shop_info
     */
    public function getShopInfo() {
        $path = '/api/v2/shop/get_shop_info';
        
        return $this->makeRequest($path, [], 'GET');
    }
    
    /**
     * Get product list
     * Endpoint: /api/v2/product/get_item_list
     */
    public function getProductList($offset = 0, $limit = 50, $item_status = 'NORMAL') {
        $path = '/api/v2/product/get_item_list';
        
        $payload = [
            'offset' => $offset,
            'limit' => $limit,
            'item_status' => $item_status
        ];
        
        return $this->makeRequest($path, $payload);
    }
    
    /**
     * Get product detail by item_id
     * Endpoint: /api/v2/product/get_item_base_info
     */
    public function getProductDetail($item_id_list) {
        $path = '/api/v2/product/get_item_base_info';
        
        $payload = [
            'item_id_list' => $item_id_list
        ];
        
        return $this->makeRequest($path, $payload);
    }
    
    /**
     * Check if response has error
     */
    public static function hasError($response) {
        return isset($response['data']['error']) && $response['data']['error'];
    }
    
    /**
     * Get error message from response
     */
    public static function getErrorMessage($response) {
        if (self::hasError($response)) {
            return isset($response['data']['message']) ? $response['data']['message'] : 'Unknown error';
        }
        return null;
    }
    
    /**
     * Get request ID from response
     */
    public static function getRequestId($response) {
        return isset($response['data']['request_id']) ? $response['data']['request_id'] : 'N/A';
    }
}

/**
 * Utility function to get Shopee API Helper instance from database
 */
function getShopeeAPIHelper($cabang = 0) {
    global $conn;
    
    $sql = "SELECT partner_id, partner_key, host, access_token, shop_id 
            FROM shopee_settings 
            WHERE cabang = " . (int)$cabang . " 
            ORDER BY id DESC LIMIT 1";
    
    $result = mysqli_query($conn, $sql);
    if (!$result || mysqli_num_rows($result) < 1) {
        throw new Exception("Shopee settings not found for cabang: " . $cabang);
    }
    
    $settings = mysqli_fetch_assoc($result);
    
    if (empty($settings['partner_id']) || empty($settings['partner_key']) || empty($settings['host'])) {
        throw new Exception("Shopee settings incomplete for cabang: " . $cabang);
    }
    
    return new ShopeeAPIHelper(
        $settings['partner_id'],
        $settings['partner_key'],
        $settings['host'],
        $settings['access_token'],
        $settings['shop_id']
    );
}
?>
