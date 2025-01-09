<?php
// includes/class-dsb-jwt.php

class DSB_JWT {
    private $secret_key;
    private $jwt = null;
    
    public function __construct() {
        $this->secret_key = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : 'your-secret-key-here';
    }

    public function generate_token($user) {
        $issued_at = time();
        $expiration = $issued_at + (DAY_IN_SECONDS * 7); // Token válido por 7 días

        $payload = [
            'iss' => get_bloginfo('url'),
            'iat' => $issued_at,
            'exp' => $expiration,
            'user' => [
                'id' => $user->ID,
                'email' => $user->user_email,
                'roles' => $user->roles
            ]
        ];

        $token = JWT::encode($payload, $this->secret_key, 'HS256');
        return $token;
    }

    public function validate_token($token) {
        try {
            $decoded = JWT::decode($token, new Key($this->secret_key, 'HS256'));
            return $decoded;
        } catch (Exception $e) {
            return false;
        }
    }
}