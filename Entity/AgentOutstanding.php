<?php

namespace Terminalbd\KpiBundle\Entity;

use App\Entity\Admin\Location;
use App\Entity\Core\Agent;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;


/**
 * AgentOrder
 *
 * @ORM\Table(name="kpi_agent_outstanding")
 * @ORM\Entity(repositoryClass="Terminalbd\KpiBundle\Repository\AgentOutstandingRepository")
 */
class AgentOutstanding
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
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\Agent" , inversedBy="outstanding")
     */
    private $agent;


    /**
     * @var float
     * @ORM\Column( type="float", nullable=true)
     */
    private $limitAmount;
    
    /**
     * @var float
     * @ORM\Column(name="actual_amount", type="float", nullable=true)
     */
    private $actualAmount;
    /**
     * @var float
     * @ORM\Column(name="outstanding", type="float", nullable=true)
     */
    private $outstanding;

    /**
     * @var Location
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Admin\Location" , inversedBy="kpiSetup")
     */
    private $district;

    /**
     * @var string
     * @ORM\Column(name="month", type="string", nullable=true)
     */
    private $month;


    /**
     * @var string
     * @ORM\Column(name="year", type="string", nullable=true)
     */
    private $year;

    /**
     * @var \DateTime
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $createdAt;

    /**
     * @var \DateTime
     * @ORM\Column(name="updated_at", type="datetime", nullable = true)
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
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return Agent
     */
    public function getAgent()
    {
        return $this->agent;
    }

    /**
     * @param Agent $agent
     */
    public function setAgent($agent)
    {
        $this->agent = $agent;
    }

    /**
     * @return float
     */
    public function getActualAmount()
    {
        return $this->actualAmount;
    }

    /**
     * @param float $actualAmount
     */
    public function setActualAmount($actualAmount)
    {
        $this->actualAmount = $actualAmount;
    }

    /**
     * @return float
     */
    public function getOutstanding()
    {
        return $this->outstanding;
    }

    /**
     * @param float $outstanding
     */
    public function setOutstanding($outstanding)
    {
        $this->outstanding = $outstanding;
    }

    /**
     * @return Location
     */
    public function getDistrict()
    {
        return $this->district;
    }

    /**
     * @param Location $district
     */
    public function setDistrict($district)
    {
        $this->district = $district;
    }

    /**
     * @return string
     */
    public function getMonth()
    {
        return $this->month;
    }

    /**
     * @param string $month
     */
    public function setMonth($month)
    {
        $this->month = $month;
    }

    /**
     * @return string
     */
    public function getYear()
    {
        return $this->year;
    }

    /**
     * @param string $year
     */
    public function setYear($year)
    {
        $this->year = $year;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTime $createdAt
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @param \DateTime $updatedAt
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
    }

    /**
     * @return bool
     */
    public function isStatus()
    {
        return $this->status;
    }

    /**
     * @param bool $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @return float
     */
    public function getLimitAmount()
    {
        return $this->limitAmount;
    }

    /**
     * @param float $limitAmount
     */
    public function setLimitAmount($limitAmount)
    {
        $this->limitAmount = $limitAmount;
    }




}
