<?php
declare(strict_types=1);

namespace App\Domain\TutorialCategory;

use App\Entity\TutorialCategory;
use App\Exception\EntityException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;

class TutorialCategoryService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @throws EntityException
     */
    public function getCategory(int $categoryId): TutorialCategory
    {
        $category = $this->entityManager->getRepository('App:TutorialCategory')->find($categoryId);

        if (!$category){
            throw EntityException::notFound();
        }

        return $category;
    }

    public function updateCategoryTitle(TutorialCategory $category, string $title): void
    {
        $category->setTitle($title);
        $this->entityManager->persist($category);
        $this->entityManager->flush();
    }
}