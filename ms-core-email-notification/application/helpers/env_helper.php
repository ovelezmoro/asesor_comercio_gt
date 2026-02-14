<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Get environment variable value
 *
 * @param string $key The environment variable key
 * @param mixed $default The default value if key doesn't exist
 * @return mixed
 */
if (!function_exists('env'))
{
    function env($key, $default = null)
    {
        // Try $_ENV first (Dotenv default)
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        
        // Fallback to $_SERVER
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        
        // Fallback to getenv()
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        
        return $default;
    }
}
