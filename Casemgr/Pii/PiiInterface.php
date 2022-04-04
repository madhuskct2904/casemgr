<?php
namespace Casemgr\Pii;

/**
 * Interface PiiInterface
 *
 * @package Casemgr\Pii
 */
interface PiiInterface
{
	/**
	 * Encrypt string
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	public function encrypt(string $string): string;

	/**
	 * Decrypt hash
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	public function decrypt(string $string): string;

	/**
	 * Compare 2 strings
	 *
	 * @param string $string
	 * @param string $encrypted
	 *
	 * @return bool
	 */
	public function compare(string $string, string $encrypted): bool;
}