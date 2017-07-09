<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__FILE__).'/../../includes/settings.php';
require_once dirname(__FILE__).'/../../includes/db_connection.php';
require_once dirname(__FILE__).'/../../modules/User.php';
require_once dirname(__FILE__).'/../../modules/Utils.php';

class AuthenticationTest extends TestCase
{
    private static $email;
    private static $password;
    private static $session_token;
    private static $user_id;

    public static function setUpBeforeClass()
    {
        global $DB_CONNECT;

        $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
        $charactersLength = strlen($characters);
        $temp_mail = '';
        for ($i = 0; $i < 7; $i++) {
            $temp_mail .= $characters[rand(0, $charactersLength - 1)];
        }
        $temp_mail = $temp_mail.'@domain.com';

        $name = 'FirstAide User';
        $password = 'somerandompassword';
        $country = 'UG';
        $password_hash = sha1(
            substr($temp_mail, 0, strlen($temp_mail)) .
                substr($password, 0, strlen($password)) .
                substr($temp_mail, strlen($temp_mail)) .
                substr($password, strlen($password))
        );
        $username = strtolower(str_replace(' ', '_', $name));
        $stmt = $DB_CONNECT->prepare(
            "
				INSERT INTO `users` (
					`email`,
					`name`,
					`password`,
					`username`,
					`country`
				) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'sssss',
            $temp_mail,
            $name,
            $password_hash,
            $username,
            $country
        );
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        $stmt = $DB_CONNECT->prepare("SELECT * FROM `users` WHERE `email` = ?");
        $stmt->bind_param('s', $temp_mail);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        $user_id = 0;
        if (!empty($row) && isset($row['user_id'])) {
            $user_id = $row['user_id'];
        } else {
            echo "setUpBeforeClass: UserID not found".PHP_EOL;
        }

              $session_token = '';
        for ($i = 0; $i < 10; $i++) {
             $session_token .= $characters[rand(0, $charactersLength - 1)];
        }

              $session_token = md5($session_token);
              $stmt = $DB_CONNECT->prepare("INSERT INTO `user_session` (`session_token`, `user_id`) VALUES (?, ?)");
              $stmt->bind_param('si', $session_token, $user_id);
              $stmt->execute();
              $affected_rows = $stmt->affected_rows;
              $stmt->close();

              echo $affected_rows > 0 ? '' : 'setUpBeforeClass: unable to insert session token';

              self::$email = $temp_mail;
              self::$password = $password;
              self::$session_token = $session_token;
              self::$user_id = $user_id;

              echo "Testing Authentication module".PHP_EOL;
    }

    public function testValidEmailAddress()
    {
        echo "with valid email address".PHP_EOL;
        $Auth = Authentication::withEmailPassword(self::$email, self::$password);
        $this->assertNotNull($Auth);
        $this->assertTrue($Auth->isValid());
        $this->assertEquals($Auth->getUserId(), self::$user_id);
    }

    public function testInvalidEmailAddress()
    {
        echo "with invalid email address".PHP_EOL;
        $Auth = Authentication::withEmailPassword('invalid', self::$password);
        $this->assertNull($Auth);
    }

    public function testValidSessionToken()
    {
        echo "with valid session token".PHP_EOL;
        $Auth = Authentication::withSessionToken(self::$session_token);
        $this->assertNotNull($Auth);
        $this->assertTrue($Auth->isValid());
        $this->assertEquals($Auth->getUserId(), self::$user_id);
    }

    public function testInvalidSessionToken()
    {
        echo "with invalid session token".PHP_EOL;
        $Auth = Authentication::withSessionToken('invalid');
        $this->assertNull($Auth);
    }

    public function testGenerateSessionToken()
    {
        echo "generate session token".PHP_EOL;
        $this->assertNotEquals(
            Authentication::generateSessionToken(),
            Authentication::generateSessionToken()
        );
    }

    public function testCreateSessionWithValidUserId()
    {
        echo "generate session token".PHP_EOL;
        $this->assertNotEquals(
            Authentication::createSession(self::$user_id),
            self::$session_token
        );
    }

    public function testCreateSessionWithInvalidUserId()
    {
        echo "generate session token".PHP_EOL;
        $this->assertFalse(
            Authentication::createSession(0)
        );
    }

    public static function tearDownAfterClass()
    {
        global $DB_CONNECT;

        echo 'tearDownAfterClass: destroyed everything'.PHP_EOL;

        $stmt = $DB_CONNECT->prepare("DELETE FROM `users` WHERE `user_id` = ?");
        $stmt->bind_param('i', self::$user_id);
        $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();

        $stmt = $DB_CONNECT->prepare("DELETE FROM `user_session` WHERE `user_id` = ?");
        $stmt->bind_param('i', self::$user_id);
        $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
    }
}
