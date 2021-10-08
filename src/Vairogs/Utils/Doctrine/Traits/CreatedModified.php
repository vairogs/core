<?php declare(strict_types = 1);

namespace Vairogs\Utils\Doctrine\Traits;

use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Exception;

trait CreatedModified
{
    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    /**
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    protected ?DateTimeInterface $creationDate = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    /**
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    protected ?DateTimeInterface $modificationDate = null;

    public function getModificationDate(): DateTimeInterface
    {
        return $this->modificationDate;
    }

    public function setModificationDate(DateTimeInterface $modificationDate): static
    {
        $this->modificationDate = $modificationDate;

        return $this;
    }

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     *
     * @throws Exception
     */
    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updatedTimestamps(): static
    {
        $this->setModificationDate(modificationDate: new DateTime());

        if (null === $this->creationDate) {
            $this->setCreationDate(creationDate: new DateTime());
        }

        return $this;
    }

    public function getCreationDate(): DateTimeInterface
    {
        return $this->creationDate;
    }

    public function setCreationDate(DateTimeInterface $creationDate): static
    {
        $this->creationDate = $creationDate;

        return $this;
    }
}