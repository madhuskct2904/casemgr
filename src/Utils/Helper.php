<?php

namespace App\Utils;

use Symfony\Component\Form\Form;

/**
 * Class FormHelper
 * @package App\Utils
 */
class Helper
{
    /**
     * Recursive helper who return all found errors in a form
     *
     * @param Form $form
     *
     * @return array
     */
    public static function getFormErrors(Form $form)
    {
        $errors = [];

        foreach ($form->getErrors() as $key => $error) {
            $template = $error->getMessageTemplate();
            $parameters = $error->getMessageParameters();

            $errors[$key] = strtr($template, $parameters);
        }

        if ($form instanceof Form) {
            foreach ($form as $child) {
                if ($child instanceof Form && !$child->isValid()) {
                    $errors[$child->getName()] = self::getFormErrors($child);
                }
            }
        }

        return $errors;
    }

    /**
     * @param int $length
     * @param string $chars
     *
     * @return string
     */
    public static function generateCode($length = 6, $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ123456789")
    {
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[mt_rand(0, strlen($chars) - 1)];
        }

        return $code;
    }

    /**
     * @param $password
     * @param $firstName
     * @param $lastName
     * @param $oldPassword - hashed!
     * @param $encoder
     * @return bool|string false if success | string if error
     */
    public static function validatePassword($password, $firstName, $lastName, $oldPassword = null, $encoder = null)
    {
        $firstName = explode(' ', $firstName);
        $lastName = explode(' ', $lastName);
        $names = array_merge($lastName, $firstName);

        foreach ($names as $name) {
            if (strlen($value = preg_replace('/[^a-zA-Z]+/', '', $name)) > 1) {
                if (!preg_match('/^((?!' . $value . ').)*$/i', $password)) {
                    return 'Invalid Password.'; //'Password can not contains your name.';
                }
            }
        }

        if (!preg_match('/^(?=.*\d)(?=.*[A-Z])(?=.*[a-z])(?=.*[!@#$%^&*()])[0-9A-Za-z!@#$%^&*()]{6,16}$/', $password)) {
            return 'Invalid Password.'; //'The password does not meet the requirements.';
        }

        if ($encoder !== null && $oldPassword !== null) {
            if ($encoder->isPasswordValid($oldPassword, $password, null)) {
                return 'Your password must be different than your current password.'; //'The old and new password must be different.';
            }
        }

        return false;
    }

    /**
     * @param array $data
     * @param string $delimiter
     * @return string
     */
    public static function csvConvert(array $data, $delimiter = ','): string
    {
        $content = '';

        foreach ($data as $row) {
            foreach ($row as $k => $v) {
                if (is_object($v) || is_array($v)) {
                    $row[$k] = '---';
                }
            }

            $content .= implode($delimiter, $row) . "\n";
        }

        return $content;
    }

    /**
     * @param string|null $phone
     * @return null|string
     */
    public static function convertPhone(?string $phone): string
    {
        if (true === empty($phone)) {
            return '';
        }
        
        if ($phone[0] === '+'){
            return $phone;
        }

        $number = preg_replace('/[^0-9]/', '', $phone);

        if ($number === '') {
            return '';
        }

        if ($number[0] !== '1') {
            $number = sprintf('1%s', $number);
        }

        return sprintf('%s' . $number, '+');
    }

    /**
     * @param $remote_tz
     * @param null $origin_tz
     * @return int
     */
    public static function getTimezoneOffset($remote_tz, $origin_tz = null)
    {
        if ($origin_tz === null) {
            if (!is_string($origin_tz = date_default_timezone_get())) {
                return 0;
            }
        }

        $origin_dtz = new \DateTimeZone($origin_tz);
        $remote_dtz = new \DateTimeZone($remote_tz);
        $origin_dt = new \DateTime("now", $origin_dtz);
        $remote_dt = new \DateTime("now", $remote_dtz);
        $offset = $origin_dtz->getOffset($origin_dt) - $remote_dtz->getOffset($remote_dt);

        return $offset / 3600;
    }

    public static function generateRandomId()
    {
        return bin2hex(openssl_random_pseudo_bytes(20));
    }
}
