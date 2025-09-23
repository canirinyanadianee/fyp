<?php
/**
 * Authentication and Authorization Functions
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if a user is logged in
 * @return bool True if user is logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require user to be logged in
 * Redirects to login page if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header('Location: /login.php');
        exit();
    }
}

/**
 * Require specific user role(s)
 * @param array|string $roles Single role or array of allowed roles
 * @param bool $redirect Whether to redirect or return boolean
 * @return bool True if user has required role, false otherwise
 */
function requireRole($roles, $redirect = true) {
    if (!isLoggedIn()) {
        if ($redirect) {
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
            $_SESSION['error'] = 'Please log in to access this page.';
            header('Location: /login.php');
            exit();
        }
        return false;
    }

    $userRole = $_SESSION['role'] ?? null;
    $allowedRoles = is_array($roles) ? $roles : [$roles];
    
    if (!in_array($userRole, $allowedRoles)) {
        if ($redirect) {
            $_SESSION['error'] = 'You do not have permission to access this page.';
            header('Location: /index.php');
            exit();
        }
        return false;
    }
    
    return true;
}

/**
 * Check if current user has a specific role
 * @param string $role Role to check
 * @return bool True if user has the role, false otherwise
 */
function hasRole($role) {
    return ($_SESSION['role'] ?? null) === $role;
}

/**
 * Check if current user has any of the specified roles
 * @param array $roles Array of roles to check
 * @return bool True if user has any of the roles, false otherwise
 */
function hasAnyRole($roles) {
    $userRole = $_SESSION['role'] ?? null;
    return in_array($userRole, $roles);
}

/**
 * Get current user ID
 * @return int|null User ID or null if not logged in
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 * @return string|null User role or null if not logged in
 */
function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

/**
 * Log in a user
 * @param int $userId User ID
 * @param string $role User role
 * @param array $additionalData Additional user data to store in session
 * @return void
 */
function loginUser($userId, $role, $additionalData = []) {
    $_SESSION['user_id'] = $userId;
    $_SESSION['role'] = $role;
    $_SESSION['last_activity'] = time();
    
    // Store additional user data in session
    foreach ($additionalData as $key => $value) {
        $_SESSION[$key] = $value;
    }
    
    // Regenerate session ID for security
    session_regenerate_id(true);
}

/**
 * Log out current user
 * @return void
 */
function logoutUser() {
    // Unset all session variables
    $_SESSION = [];
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
}

/**
 * Check if session has expired
 * @param int $timeoutSeconds Number of seconds before session expires
 * @return bool True if session is still valid, false if expired
 */
function isSessionValid($timeoutSeconds = 1800) {
    if (!isset($_SESSION['last_activity'])) {
        return false;
    }
    
    // Check if session has expired
    if (time() - $_SESSION['last_activity'] > $timeoutSeconds) {
        return false;
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Require CSRF token validation
 * @param string $formName Name of the form for token generation
 * @return void
 */
function requireCSRF($formName = 'default') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['_token'] ?? '';
        if (!validateCSRFToken($token, $formName)) {
            http_response_code(403);
            die('Invalid CSRF token');
        }
    }
}

/**
 * Generate a CSRF token
 * @param string $formName Name of the form
 * @return string CSRF token
 */
function generateCSRFToken($formName = 'default') {
    if (empty($_SESSION['csrf_tokens'][$formName])) {
        $_SESSION['csrf_tokens'][$formName] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_tokens'][$formName];
}

/**
 * Validate a CSRF token
 * @param string $token Token to validate
 * @param string $formName Name of the form
 * @return bool True if token is valid, false otherwise
 */
function validateCSRFToken($token, $formName = 'default') {
    if (empty($_SESSION['csrf_tokens'][$formName])) {
        return false;
    }
    
    $isValid = hash_equals($_SESSION['csrf_tokens'][$formName], $token);
    
    // Regenerate token after validation
    unset($_SESSION['csrf_tokens'][$formName]);
    
    return $isValid;
}

/**
 * Generate a CSRF token input field
 * @param string $formName Name of the form
 * @return string HTML input field with CSRF token
 */
function csrfField($formName = 'default') {
    $token = generateCSRFToken($formName);
    return '<input type="hidden" name="_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Check if current request is an AJAX request
 * @return bool True if AJAX request, false otherwise
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Send JSON response
 * @param mixed $data Data to encode as JSON
 * @param int $statusCode HTTP status code
 * @return void
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/**
 * Redirect to a URL
 * @param string $url URL to redirect to
 * @param int $statusCode HTTP status code for redirect
 * @return void
 */
function redirect($url, $statusCode = 302) {
    header("Location: $url", true, $statusCode);
    exit();
}
?>
