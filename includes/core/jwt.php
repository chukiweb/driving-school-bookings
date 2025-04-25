<?php
//includes/class-dsb-jwt.php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class DSB_JWT
{
    private $secret_key;
    private $option_name = 'dsb_jwt_secret_key';

    public function __construct()
    {
        $this->secret_key = $this->get_or_create_secret_key();
    }

    private function get_or_create_secret_key()
    {
        $secret_key = get_option($this->option_name);
        if (!$secret_key) {
            $secret_key = bin2hex(random_bytes(32));
            update_option($this->option_name, $secret_key);
        }
        return $secret_key;
    }

    public function generate_token($user)
    {
        $issued_at = time();
        $expiration = $issued_at + (DAY_IN_SECONDS * 7);
        $payload = [
            'iss' => get_bloginfo('url'),
            'iat' => $issued_at,
            'exp' => $expiration,
            'user' => [
                'id' => $user->ID,
                'email' => $user->user_email,
                'roles' => $user->roles[0],
                'url' => $user->roles[0] === 'teacher' ? '/profesor' : '/alumno',
            ]
        ];
        return JWT::encode($payload, $this->secret_key, 'HS256');
    }

    public function store_token(string $token) {
        if (!session_id()) {
            session_start();
        }
        // Guarda en la sesión de PHP
        $_SESSION['jwt_token'] = $token;
        // Y también en cookie para que PHP la lea en el front
        setcookie(
            'jwt_token',
            $token,
            time() + (DAY_IN_SECONDS * 7),
            '/',
            '',        // dominio (deja vacío para auto)
            false,     // https only?
            true       // httponly
        );
    }

    public function validate_token($token)
    {
        try {
            return JWT::decode($token, new Key($this->secret_key, 'HS256'));
        } catch (Exception $e) {
            return false;
        }
    }
}
