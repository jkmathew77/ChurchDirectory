<?php
/**
 * AES-256-CBC encryption for sensitive PII fields.
 * Uses AUTH_KEY and AUTH_SALT from wp-config.php as the encryption key source.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CD_Encryption {

    private static $cipher = 'aes-256-cbc';

    /**
     * Derive the encryption key from WordPress salts.
     */
    private static function get_key() {
        $key_material = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'cd-fallback-key';
        $salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : 'cd-fallback-salt';
        return hash( 'sha256', $key_material . $salt, true );
    }

    /**
     * Encrypt a plaintext value.
     *
     * @param string $plaintext The value to encrypt.
     * @return string Base64-encoded ciphertext with IV prepended.
     */
    public static function encrypt( $plaintext ) {
        if ( empty( $plaintext ) ) {
            return '';
        }

        if ( ! function_exists( 'openssl_encrypt' ) ) {
            // Fail closed rather than silently storing an OAuth secret or other
            // sensitive setting as plaintext after a PHP/hosting change.
            if ( class_exists( 'CD_Logger' ) ) {
                CD_Logger::error( 'OpenSSL is unavailable; sensitive value was not saved.' );
            }
            return '';
        }

        $key = self::get_key();
        $iv_length = openssl_cipher_iv_length( self::$cipher );
        $iv = openssl_random_pseudo_bytes( $iv_length );

        $ciphertext = openssl_encrypt( $plaintext, self::$cipher, $key, OPENSSL_RAW_DATA, $iv );

        if ( false === $ciphertext ) {
            return '';
        }

        // Prepend IV to ciphertext and base64 encode
        return base64_encode( $iv . $ciphertext );
    }

    /**
     * Decrypt a ciphertext value.
     *
     * @param string $encrypted Base64-encoded ciphertext with IV prepended.
     * @return string The decrypted plaintext, or empty string on failure.
     */
    public static function decrypt( $encrypted ) {
        if ( empty( $encrypted ) ) {
            return '';
        }

        // Backward compatibility for any legacy values saved by an older build.
        if ( strpos( $encrypted, 'PLAIN:' ) === 0 ) {
            $decoded = base64_decode( substr( $encrypted, 6 ), true );
            return false === $decoded ? '' : $decoded;
        }

        if ( ! function_exists( 'openssl_decrypt' ) ) {
            return '';
        }

        $key = self::get_key();
        $data = base64_decode( $encrypted, true );

        if ( false === $data ) {
            return '';
        }

        $iv_length = openssl_cipher_iv_length( self::$cipher );

        // Safety check for IV length vs data length
        if ( strlen( $data ) < $iv_length ) {
            return '';
        }

        $iv = substr( $data, 0, $iv_length );
        $ciphertext = substr( $data, $iv_length );

        $plaintext = openssl_decrypt( $ciphertext, self::$cipher, $key, OPENSSL_RAW_DATA, $iv );

        return ( false === $plaintext ) ? '' : $plaintext;
    }
}
