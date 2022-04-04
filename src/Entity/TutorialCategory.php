<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * TutorialCategory
 *
 * @ORM\Table(name="tutorial_category")
 * @ORM\Entity(repositoryClass="App\Repository\TutorialCategoryRepository")
 */
class TutorialCategory
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="title", type="string", length=255)
     */
    private $title;

    /**
     * @var int
     *
     * @ORM\Column(name="sort", type="integer")
     */
    private $sort;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Tutorial", mappedBy="category", cascade={"remove"})
     */
    protected $tutorials;

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set title.
     *
     * @param string $title
     *
     * @return TutorialCategory
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get title.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set sort.
     *
     * @param int $sort
     *
     * @return TutorialCategory
     */
    public function setSort($sort)
    {
        $this->sort = $sort;

        return $this;
    }

    /**
     * Get sort.
     *
     * @return int
     */
    public function getSort()
    {
        return $this->sort;
    }

    /**
     * @return mixed
     */
    public function getTutorials()
    {
        return $this->tutorials;
    }

    /**
     * @param mixed $tutorials
     */
    public function setTutorials($tutorials): void
    {
        $this->tutorials = $tutorials;
    }


}
