<?php

namespace OkhubJwtAuth\Services;

/**
 * User service for authentication operations
 */
class UserService
{
    /**
     * Find user by email
     */
    public function findByEmail($email)
    {
        return \get_user_by('email', \sanitize_email($email));
    }

    /**
     * Find user by username
     */
    public function findByUsername($username)
    {
        return \get_user_by('login', \sanitize_user($username));
    }

    /**
     * Find user by ID
     */
    public function findById($userId)
    {
        return \get_user_by('ID', intval($userId));
    }

    /**
     * Check if email exists
     */
    public function emailExists($email)
    {
        return \email_exists(\sanitize_email($email));
    }

    /**
     * Check if username exists
     */
    public function usernameExists($username)
    {
        return \username_exists(\sanitize_user($username));
    }

    /**
     * Create new user
     */
    public function create($userData, $emailVerified = true)
    {
        $userData = array_map('sanitize_text_field', $userData);

        $userData['role'] = isset($userData['role']) ? $userData['role'] : 'subscriber';

        $userId = \wp_create_user(
            $userData['username'],
            $userData['password'],
            $userData['email']
        );
        if (isset($userData['customer_code'])) {
            \update_user_meta($userId, 'customer_code', $userData['customer_code']);
        }
        if (\is_wp_error($userId)) {
            return $userId;
        }

        // Update additional user data
        if (isset($userData['first_name'])) {
            \wp_update_user([
                'ID' => $userId,
                'first_name' => $userData['first_name'],
                'display_name' => $userData['first_name']
            ]);
        }

        // Set email verification status
        \update_user_meta($userId, 'okhub_email_verified', $emailVerified);

        return \get_user_by('ID', $userId);
    }

    /**
     * Check if user email is verified
     */
    public function isEmailVerified($userId)
    {
        $verified = \get_user_meta($userId, 'okhub_email_verified', true);
        return $verified === '1' || $verified === true;
    }

    /**
     * Mark user email as verified
     */
    public function verifyEmail($userId)
    {
        return \update_user_meta($userId, 'okhub_email_verified', true);
    }

    /**
     * Get public user info (safe to expose)
     */
    public function getPublicInfo($user)
    {
        if (!$user || !($user instanceof \WP_User)) {
            return null;
        }

        $userInfo = [
            'id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'display_name' => $user->display_name,
            'customer_code' => \get_user_meta($user->ID, 'customer_code', true),
            'registered' => $user->user_registered,
            'roles' => $user->roles,
            'capabilities' => array_keys($user->allcaps)
        ];

        // Add email verification status if email verification is enabled
        $emailVerificationEnabled = \get_option('okhub_jwt_enable_email_verification', true);
        if ($emailVerificationEnabled) {
            $userInfo['email_verified'] = $this->isEmailVerified($user->ID);
        }

        // Add custom meta fields
        $customFields = [
            'phone' => \get_user_meta($user->ID, 'phone', true),
            'gender' => \get_user_meta($user->ID, 'gender', true),
            'date_of_birth' => \get_user_meta($user->ID, 'date_of_birth', true)
        ];

        $userInfo = array_merge($userInfo, array_filter($customFields));

        return $userInfo;
    }

    /**
     * Update user profile
     */
    public function updateProfile($userId, $data)
    {
        $user = $this->findById($userId);
        if (!$user) {
            return new \WP_Error('user_not_found', 'User not found');
        }

        $updateData = ['ID' => $userId];

        // Update basic fields
        if (isset($data['first_name'])) {
            $updateData['first_name'] = \sanitize_text_field($data['first_name']);
            $updateData['display_name'] = \sanitize_text_field($data['first_name']);
        }

        if (isset($data['last_name'])) {
            $updateData['last_name'] = \sanitize_text_field($data['last_name']);
        }

        $result = \wp_update_user($updateData);
        if (\is_wp_error($result)) {
            return $result;
        }

        // Update custom meta fields
        $this->updateUserMeta($userId, $data);

        return $this->findById($userId);
    }

    /**
     * Update user meta fields
     */
    private function updateUserMeta($userId, $data)
    {
        // Update phone number
        if (isset($data['phone'])) {
            \update_user_meta($userId, 'phone', \sanitize_text_field($data['phone']));
        }

        // Update gender
        if (isset($data['gender'])) {
            $gender = \sanitize_text_field($data['gender']);
            if (in_array($gender, ['male', 'female', 'other'])) {
                \update_user_meta($userId, 'gender', $gender);
            }
        }

        // Update date of birth
        if (isset($data['date_of_birth'])) {
            $dateOfBirth = \sanitize_text_field($data['date_of_birth']);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOfBirth)) {
                \update_user_meta($userId, 'date_of_birth', $dateOfBirth);
            }
        }

        // Update customer code
        if (isset($data['customer_code'])) {
            \update_user_meta($userId, 'customer_code', \sanitize_text_field($data['customer_code']));
        }
    }

    /**
     * Change user password
     */
    public function changePassword($userId, $newPassword)
    {
        $user = $this->findById($userId);
        if (!$user) {
            return new \WP_Error('user_not_found', 'User not found');
        }

        $result = \wp_set_password($newPassword, $userId);
        if (\is_wp_error($result)) {
            return $result;
        }

        return true;
    }

    /**
     * Verify user password
     */
    public function verifyPassword($userId, $password)
    {
        $user = $this->findById($userId);
        if (!$user) {
            return false;
        }

        return \wp_check_password($password, $user->user_pass, $userId);
    }
}