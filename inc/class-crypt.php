<?php

class BF_User_Exporter_Crypt {

    private $cipher = "AES-128-ECB";

    public function encrypt($password) {
        $key = $this->get_key();
        return openssl_encrypt($password, $this->cipher, $key);
    }

    public function decrypt($encrypted_password) {
        $key = $this->get_key();
        return openssl_decrypt($encrypted_password, $this->cipher, $key);
    }

    private function get_key() {
        $key = get_option('bf_user_export_crypt_key');
        if (empty($key)) {
            $key = ''; // デフォルトキー
        }
        return $key;
    }
}
