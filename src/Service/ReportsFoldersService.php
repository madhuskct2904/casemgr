<?php

namespace App\Service;

use App\Entity\Accounts;
use App\Entity\ReportFolder;
use App\Domain\FormValues\ReportsFoldersServiceException;
use Doctrine\ORM\EntityManagerInterface;

class ReportsFoldersService
{
    protected $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function createFolder(string $name, Accounts $account, $parentId = null): void
    {
        if ($parentId) {
            $parentFolder = $this->em->getRepository('App:ReportFolder')->findOneBy(['id' => $parentId]);
        }

        if (!$parentId || !$parentFolder) {
            $parentFolder = $this->em->getRepository('App:ReportFolder')->findOneBy(['name' => 'account' . $account->getId()]);
        }

        $reportFolder = new ReportFolder();
        $reportFolder->setName($name);
        $reportFolder->setParent($parentFolder);
        $this->em->persist($reportFolder);
        $this->em->flush();
    }

    public function deleteFolder(Accounts $account, int $folderId, bool $deleteContent): int
    {
        $folder = $this->em->getRepository('App:ReportFolder')->findOneBy(['id' => $folderId]);
        if (!$this->checkFolderBelongsToAccount($folder, $account)) {
            throw new ReportsFoldersServiceException('Security violation!');
        }

        $parent = $folder->getParent();
        $foldersRepository = $this->em->getRepository('App:ReportFolder');
        $children = $foldersRepository->children($folder);

        foreach ($children as $child) {
            if ($deleteContent) {
                $reports = $this->em->getRepository('App:Reports')->findBy(['folder' => $child]);
                foreach ($reports as $report) {
                    $this->em->remove($report);
                }
                $this->em->flush();
                $this->em->remove($child);
            } else {
                $child->setParent($parent);
            }

            $this->em->flush();
        }

        $reports = $this->em->getRepository('App:Reports')->findBy(['folder' => $folder]);
        foreach ($reports as $report) {
            if ($deleteContent) {
                $this->em->remove($report);
            } else {
                $report->setFolder($parent);
            }
        }
        $this->em->flush();

        $this->em->remove($folder);
        $this->em->flush();
        $this->em->clear();

        return $parent->getId();
    }


    public function getFoldersTree(Accounts $account): array
    {
        $foldersRepository = $this->em->getRepository('App:ReportFolder');
        $folder = $this->em->getRepository('App:ReportFolder')->findOneBy(['name' => 'account' . $account->getId()]);

        $options = [
            'childSort' => ['field' => 'name', 'dir' => 'desc']
        ];

        return $foldersRepository->childrenHierarchy($folder, false, $options, true);
    }

    public function getFolders(Accounts $account): array
    {
        $tree = $this->getFoldersTree($account);
        $sortedFlattenTree = $this->parseNode($tree, [])[1];

        if (isset($sortedFlattenTree[0]) && $sortedFlattenTree[0]['name'] == 'account'.$account->getId()) {
            $sortedFlattenTree[0]['name'] = 'Reports List';
        }

        return $sortedFlattenTree;
    }

    private function sortNode(array $node): array
    {
        $name = array_column($node, 'name');
        array_multisort($name, SORT_ASC, $node);
        return $node;
    }

    private function parseNode(array $node, array $flat): array
    {
        $node = $this->sortNode($node);
        foreach ($node as $idx => $leaf) {
            $flatLeaf = $leaf;
            unset($flatLeaf['__children']);
            $flat[] = $flatLeaf;
            if (count($leaf['__children'])) {
                $parsedNode = $this->parseNode($leaf['__children'], $flat);
                $node[$idx]['__children'] = $parsedNode[0];
                $flat = $parsedNode[1];
            }
        }
        return [$node, $flat];
    }

    public function moveReportToFolder(Accounts $account, int $reportId, int $folderId): void
    {
        $report = $this->em->getRepository('App:Reports')->findOneBy([
            'id'      => $reportId,
            'account' => $account
        ]);

        if (!$report) {
            throw new ReportsFoldersServiceException('Report not exists!');
        }

        $folder = $this->em->getRepository('App:ReportFolder')->findOneBy([
            'id' => $folderId
        ]);

        if (!$folder) {
            throw new ReportsFoldersServiceException('Folder not exists!');
        }

        if (!$this->checkFolderBelongsToAccount($folder, $account)) {
            throw new ReportsFoldersServiceException('Security violation!');
        }

        $report->setFolder($folder);
        $this->em->flush();
    }

    private function checkFolderBelongsToAccount(ReportFolder $folder, Accounts $account): bool
    {
        $ancestors = $folder->getRoot();

        if (!$ancestors->getName() == 'account' . $account->getId()) {
            return false;
        }

        return true;
    }

    public function moveFolderToFolder(Accounts $account, int $sourceFolderId, int $targetFolderId): void
    {
        $sourceFolder = $this->em->getRepository('App:ReportFolder')->findOneBy(['id' => $sourceFolderId]);
        $targetFolder = $this->em->getRepository('App:ReportFolder')->findOneBy(['id' => $targetFolderId]);

        if (!$this->checkFolderBelongsToAccount($sourceFolder, $account)
            || !$this->checkFolderBelongsToAccount($targetFolder, $account)) {
            throw new \Exception('Security violation!');
        }

        $sourceFolder->setParent($targetFolder);
        $this->em->flush();
    }

    public function renameFolder(Accounts $account, int $folderId, string $newName): void
    {
        $folder = $this->em->getRepository('App:ReportFolder')->findOneBy(['id' => $folderId]);

        if (!$this->checkFolderBelongsToAccount($folder, $account)) {
            throw new \Exception('Security violation!');
        }

        $folder->setName($newName);
        $this->em->flush();
    }
}
