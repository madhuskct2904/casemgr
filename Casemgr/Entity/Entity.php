<?php
namespace Casemgr\Entity;

use Casemgr\Pii\PiiInterface;

/**
 * Class Entity
 *
 * @package Casemgr\Entity
 */
class Entity
{
	private $pii;

	/**
	 * @param PiiInterface $pii
	 */
	public function setPii(PiiInterface $pii)
	{
		$this->pii = $pii;
	}

	/**
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	public function encrypt($value)
	{
		if($this->pii === null) {
			return $value;
		}

		return $this->pii->encrypt($value);
	}

	/**
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	public function decrypt($value)
	{
		if($this->pii === null) {
			return $value;
		}

		return $this->pii->decrypt($value);
	}
}
