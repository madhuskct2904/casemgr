<?php
namespace Casemgr\Pii;

use Doctrine\ORM\EntityManagerInterface;
use PHPCrypto\Symmetric;

/**
 * Class Pii
 *
 * @package Casemgr\Pii
 */
class Pii implements PiiInterface
{
    /**
     * @var EntityManager 
     */
	private $em;

	/**
	 * @var Symmetric
	 */
    private $cipher;

	/**
	 * Pii constructor.
	 *
	 * @param EntityManagerInterface $em
	 * @param string $securityKey
	 * @param Symmetric $cipher
	 *
	 * @throws PiiException
	 */
    public function __construct(EntityManagerInterface $em, string $securityKey, Symmetric $cipher)
    {
        $this->em 		= $em;
        $this->cipher 	= $cipher;

		if(strlen($securityKey) < 15) {
			throw new PiiException('Invalid security key');
		}

		$this->cipher->setKey($securityKey);
    }

	/**
	 * Encrypt string
	 *
	 * @param string $string
	 *
	 * @return string
	 */
    public function encrypt(string $string): string
	{
		return base64_encode($string);
	}

	/**
	 * Decrypt hash
	 *
	 * @param string $string
	 *
	 * @return string
	 */
    public function decrypt(string $string): string
	{
		return base64_decode($string);
	}

	/**
	 * Compare 2 strings
	 *
	 * @param string $string
	 * @param string $encrypted
	 *
	 * @return bool
	 */
	public function compare(string $string, string $encrypted): bool
	{
		return $string === $this->decrypt($encrypted);
	}

    /**
     * @param int $length
     * @return string
     */
	public static function generateRandomString(int $length = 32)
    {
        $characters             = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $characters_length      = strlen($characters);
        $random_string          = '';

        for ($i = 0; $i < $length; $i++) {
            $random_string .= $characters[rand(0, $characters_length - 1)];
        }

        return $random_string;
    }
}
