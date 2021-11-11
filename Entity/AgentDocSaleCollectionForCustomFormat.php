<?php

namespace Terminalbd\KpiBundle\Entity;

use App\Entity\Admin\Location;
use App\Entity\Core\Agent;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;


/**
 * AgentOrder
 *
 * @ORM\Table(name="kpi_agent_doc_sale_collection_custom_format")
 * @ORM\Entity(repositoryClass="Terminalbd\KpiBundle\Repository\AgentDocSaleCollectionForCustomFormatRepository")
 */
class AgentDocSaleCollectionForCustomFormat

{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue
     */
    private $id;

    /**
     * @var $employeeBoard
     * @ORM\OneToOne(targetEntity="Terminalbd\KpiBundle\Entity\EmployeeBoard")
     * @ORM\JoinColumn(referencedColumnName="id", onDelete="CASCADE")
     */
    private $employeeBoard;


    /**
     * @var float
     * @ORM\Column(type="float", nullable=true)
     */
    private $sales;


    /**
     * @var float
     * @ORM\Column(type="float", nullable=true)
     */
    private $collection;

    /**
     * @var \DateTime
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * @var \DateTime
     * @ORM\Column(type="datetime", nullable = true)
     */
    private $updatedAt;


    /**
     * @var boolean
     *
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $status = true;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getEmployeeBoard()
    {
        return $this->employeeBoard;
    }

    /**
     * @param mixed $employeeBoard
     */
    public function setEmployeeBoard($employeeBoard): void
    {
        $this->employeeBoard = $employeeBoard;
    }

    /**
     * @return float
     */
    public function getSales(): float
    {
        return $this->sales;
    }

    /**
     * @param float $sales
     */
    public function setSales(float $sales): void
    {
        $this->sales = $sales;
    }

    /**
     * @return float
     */
    public function getCollection(): float
    {
        return $this->collection;
    }

    /**
     * @param float $collection
     */
    public function setCollection(float $collection): void
    {
        $this->collection = $collection;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTime $createdAt
     */
    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return \DateTime
     */
    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    /**
     * @param \DateTime $updatedAt
     */
    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    /**
     * @return bool
     */
    public function isStatus(): bool
    {
        return $this->status;
    }

    /**
     * @param bool $status
     */
    public function setStatus(bool $status): void
    {
        $this->status = $status;
    }

}
