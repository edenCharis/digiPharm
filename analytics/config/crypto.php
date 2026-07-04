<?php
/**
 * Symmetric encryption for sensitive fields (SSH/DB passwords).
 * Uses AES-256-CBC with ENCRYPTION_KEY from env.php.
 */

function _ai_enc_key(): string
{
    $raw = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'digipharmai_fallback_key_change_me';
    return substr(hash('sha256', $raw, true), 0, 32);
}

function ai_encrypt(string $plaintext): string
{
    if ($plaintext === '') return '';
    $iv  = openssl_random_pseudo_bytes(16);
    $enc = openssl_encrypt($plaintext, 'AES-256-CBC', _ai_enc_key(), OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $enc);
}

function ai_decrypt(string $ciphertext): string
{
    if ($ciphertext === '') return '';
    $raw = base64_decode($ciphertext);
    $iv  = substr($raw, 0, 16);
    $enc = substr($raw, 16);
    return openssl_decrypt($enc, 'AES-256-CBC', _ai_enc_key(), OPENSSL_RAW_DATA, $iv) ?: '';
}
