<?php

namespace App;

use Omeka\Mvc\Application;

class Omeka
{
    private static $app = null;

    private static $authEmail = null;

    private static $authPassword = null;

    /**
     * Bootstrap Omeka application.
     */
    public static function bootstrap(): void
    {
        $publicDir = __DIR__ . '/../public';
        $bootstrapFile = $publicDir . '/bootstrap.php';
        if (file_exists($bootstrapFile)) {
            require $bootstrapFile;
            self::$app = Application::init(require $publicDir . '/application/config/application.config.php');
        }
    }

    /**
     * Authenticate a user.
     *
     * @param string|null $email The user email. If null, use the last used email.
     * @param string|null $password The user password. If null, use the last used password.
     */
    public static function authenticate(string $email = null, string $password = null): bool
    {
        if ($email === null) {
            $email = self::$authEmail;
        } else {
            self::$authEmail = $email;
        }
        if ($password === null) {
            $password = self::$authPassword;
        } else {
            self::$authPassword = $password;
        }

        $app = self::getApp();
        $serviceManager = $app->getServiceManager();
        /**
         * @var \Laminas\Authentication\AuthenticationService $auth
         */
        $auth = $serviceManager->get('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity($email);
        $adapter->setCredential($password);
        $result = $auth->authenticate();
        return $result->isValid();
    }

    /**
     * Get the Omeka application instance.
     *
     * @return \Laminas\Mvc\Application
     */
    public static function getApp(): \Laminas\Mvc\Application
    {
        if (self::$app === null) {
            self::bootstrap();
        }
        return self::$app;
    }

    /**
     * Reload the Omeka application instance.
     *
     * @return \Laminas\Mvc\Application
     */
    public static function reloadApp(): \Laminas\Mvc\Application
    {
        self::$app = null;
        return self::getApp();
    }
}
