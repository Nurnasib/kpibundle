<?php

namespace Terminalbd\KpiBundle\Entity;

use App\Entity\Admin\Location;
use App\Entity\Core\Agent;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;


/**
 * AgentOrder
 *
 * @ORM\Table(name="kpi_agent_outstanding_custom_format")
 * @ORM\Entity(repositoryClass="Terminalbd\KpiBundle\Repository\AgentOutstandingForCustomFormatRepository")
 */
class AgentOutstandingForCustomFormat
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
     * @var float
     * @ORM\Column( type="float", nullable=true)
     */
    private $limitAmount;
    
    /**
     * @var float
     * @ORM\Column(type="float", nullable=true)
     */
    private $actualAmount;
    /**
     * @var float
     * @ORM\Column(type="float", nullable=true)
     */
    private $outstanding;

    /**
     * @var $employeeBoard
     * @ORM\OneToOne(targetEntity="Terminalbd\KpiBundle\Entity\EmployeeBoard")
     * @ORM\JoinColumn(referencedColumnName="id", onDelete="CASCADE")
     */
    private $employeeBoard;

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
     * @return float
     */
    public function getLimitAmount(): float
    {
        return $this->limitAmount;
    }

    /**
     * @param float $limitAmount
     */
    public function setLimitAmount(float $limitAmount): void
    {
        $this->limitAmount = $limitAmount;
    }

    /**
     * @return float
     */
    public function getActualAmount(): float
    {
        return $this->actualAmount;
    }

    /**
     * @param float $actualAmount
     */
    public function setActualAmount(float $actualAmount): void
    {
        $this->actualAmount = $actualAmount;
    }

    /**
     * @return float
     */
    public function getOutstanding(): float
    {
        return $this->outstanding;
    }

    /**
     * @param float $outstanding
     */
    public function setOutstanding(float $outstanding): void
    {
        $this->outstanding = $outstanding;
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
