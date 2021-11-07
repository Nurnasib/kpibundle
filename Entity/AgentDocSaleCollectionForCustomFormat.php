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
     * @var Agent
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\Agent" , inversedBy="docSaleCustomFormat")
     */
    private $agent;

    /**
     * @var $employeeBoard
     * @ORM\ManyToOne(targetEntity="Terminalbd\KpiBundle\Entity\EmployeeBoard", inversedBy="outstandingCustomFormat")
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
     * @var string
     * @ORM\Column(type="string", nullable=true)
     */
    private $month;


    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     */
    private $year;


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
     * @return Agent
     */
    public function getAgent(): Agent
    {
        return $this->agent;
    }

    /**
     * @param Agent $agent
     */
    public function setAgent(Agent $agent): void
    {
        $this->agent = $agent;
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
     * @return string
     */
    public function getMonth(): string
    {
        return $this->month;
    }

    /**
     * @param string $month
     */
    public function setMonth(string $month): void
    {
        $this->month = $month;
    }

    /**
     * @return string
     */
    public function getYear(): string
    {
        return $this->year;
    }

    /**
     * @param string $year
     */
    public function setYear(string $year): void
    {
        $this->year = $year;
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
