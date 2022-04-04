<?php


namespace App\Service;

use App\Entity\GeneralSettings;
use App\Entity\Users;
use App\Repository\GeneralSettingsRepository;
use Doctrine\Persistence\ManagerRegistry;
use Exception;

/**
 * Class GeneralSettingsService
 * @package App\Service
 */
class GeneralSettingsService
{
    /**
     * @var ManagerRegistry
     */
    private $doctrine;

    /**
     * GeneralSettingsService constructor.
     *
     * @param ManagerRegistry $doctrine
     */
    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getAllSettings(): array
    {
        $data = $this->doctrine->getRepository('App:GeneralSettings')->findAll();

        $output = [];
        /** @var GeneralSettings $item */
        foreach ($data as $item) {
            $output[$item->getKey()] = $item->getValue();
        }

        return $output;
    }

    /**
     * @param array $data
     * @throws Exception
     */
    public function save(array $data): void
    {
        /** @var GeneralSettingsRepository $repo */
        $repo = $this->doctrine->getRepository('App:GeneralSettings');
        $em = $this->doctrine->getManager();

        foreach ($data as $key => $value) {
            /** @var GeneralSettings $item */
            $item = $repo->findOneBy(['key' => $key]);
            $item->setValue($value);
            $em->persist($item);
            $em->flush();
        }
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function isMaintenanceMode(Users $user): bool
    {
        /** @var GeneralSettings $data */
        $data = $this->doctrine->getRepository('App:GeneralSettings')->findOneBy(['key' => 'maintenance_mode']);

        if ($data->getValue() == '[]') {
            return false;
        }

        $accountsIds = json_decode($data->getValue());

        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        $disabledAccounts = $this->doctrine->getRepository('App:Accounts')->findBy(['id'=>$accountsIds]);

        foreach ($disabledAccounts as $disabledAccount) {
            if ($user->getAccounts()->contains($disabledAccount)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getMaintenanceMode(): array
    {
        $keys = [
            'maintenance_mode',
            'maintenance_message'
        ];

        $data = $this->doctrine->getRepository('App:GeneralSettings')->findByKeys($keys);

        $output = [];
        /** @var GeneralSettings $item */
        foreach ($data as $item) {
            $output[$item->getKey()] = $item->getValue();
        }

        return $output;
    }
}
