<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Tutorial
 *
 * @ORM\Table(name="tutorial")
 * @ORM\Entity(repositoryClass="App\Repository\TutorialRepository")
 */
class Tutorial
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
     * @var int
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\TutorialCategory")
     * @ORM\JoinColumn(name="category_id", referencedColumnName="id", onDelete="cascade")
     */
    private $category;

    /**
     * @var string
     *
     * @ORM\Column(name="header", type="string", length=255)
     */
    private $header;

    /**
     * @var string
     *
     * @ORM\Column(name="subheader", type="string", length=255)
     */
    private $subheader;

    /**
     * @var string
     *
     * @ORM\Column(name="thumb_file", type="string", length=255)
     */
    private $thumbFile;

    /**
     * @var string
     *
     * @ORM\Column(name="file", type="string", length=255)
     */
    private $file;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $createdAt;

    /**
     * @var int
     *
     * @ORM\Column(name="file_size", type="bigint")
     */
    private $fileSize;


    /**
     * @var string
     *
     * @ORM\Column(name="filename", type="string", length=255)
     */
    private $filename;

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
     * Set category.
     *
     * @param string $category
     *
     * @return Tutorial
     */
    public function setCategory($category)
    {
        $this->category = $category;

        return $this;
    }

    /**
     * Get category.
     *
     * @return string
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * Set header.
     *
     * @param string $header
     *
     * @return Tutorial
     */
    public function setHeader($header)
    {
        $this->header = $header;

        return $this;
    }

    /**
     * Get header.
     *
     * @return string
     */
    public function getHeader()
    {
        return $this->header;
    }

    /**
     * Set subheader.
     *
     * @param string $subheader
     *
     * @return Tutorial
     */
    public function setSubheader($subheader)
    {
        $this->subheader = $subheader;

        return $this;
    }

    /**
     * Get subheader.
     *
     * @return string
     */
    public function getSubheader()
    {
        return $this->subheader;
    }

    /**
     * Set thumbFile.
     *
     * @param string $thumbFile
     *
     * @return Tutorial
     */
    public function setThumbFile($thumbFile)
    {
        $this->thumbFile = $thumbFile;

        return $this;
    }

    /**
     * Get thumbFile.
     *
     * @return string
     */
    public function getThumbFile()
    {
        return $this->thumbFile;
    }

    /**
     * Set file.
     *
     * @param string $file
     *
     * @return Tutorial
     */
    public function setFile($file)
    {
        $this->file = $file;

        return $this;
    }

    /**
     * Get file.
     *
     * @return string
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * Set createdAt.
     *
     * @param \DateTime $createdAt
     *
     * @return Tutorial
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get createdAt.
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set fileSize.
     *
     * @param int $fileSize
     *
     * @return Tutorial
     */
    public function setFileSize($fileSize)
    {
        $this->fileSize = $fileSize;

        return $this;
    }

    /**
     * Get fileSize.
     *
     * @return int
     */
    public function getFileSize()
    {
        return $this->fileSize;
    }

    /**
     * Get filename.
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * Set filename.
     *
     * @param string $filename
     *
     * @return Tutorial
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;

        return $this;
    }

}
