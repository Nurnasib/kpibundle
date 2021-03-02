<?php

namespace Terminalbd\KpiBundle\Entity;

use App\Entity\Admin\Location;
use App\Entity\Core\Agent;
use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;


/**
 * AgentOrder
 *
 * @ORM\Table(name="kpi_agent_order")
 * @ORM\Entity(repositoryClass="Terminalbd\KpiBundle\Repository\AgentOrderRepository")
 */
class AgentOrder

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
     * @var Location
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Admin\Location" , inversedBy="agentOrder")
     */
    private $upozila;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Admin\Location")
     */
    protected $district;

    /**
     * @var Agent
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\Agent" , inversedBy="agentOrder")
     */
    private $agent;
    
    /**
     * @var DocumentUpload
     *
     * @ORM\ManyToOne(targetEntity="Terminalbd\KpiBundle\Entity\DocumentUpload" , inversedBy="agentOrder")
     * @ORM\JoinColumn(onDelete="cascade")
     */
    private $documentUpload;


    /**
     * @var MarkChart
     *
     * @ORM\ManyToOne(targetEntity="MarkChart" , inversedBy="agentOrder")
     */
    private $product;

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
     * @var float
     * @ORM\Column(name="quantity", type="float", nullable=true)
     */
    private $quantity;


    /**
     * @var float
     * @ORM\Column(name="amount", type="float", nullable=true)
     */
    private $amount;


    /**
     * @var \DateTime
     * @ORM\Column(name="created", type="datetime", nullable = true)
     */
    private $created;

    /**
     * @var \DateTime
     * @ORM\Column(name="updated", type="datetime", nullable = true)
     */
    private $updated;


    /**
     * @var boolean
     *
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $status = true;

    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }
    /**
     * @return mixed
     */
    public function getDistrict()
    {
        return $this->district;
    }

    /**
     * @param mixed $district
     */
    public function setDistrict($district)
    {
        $this->district = $district;
    }

    /**
     * @return Location
     */
    public function getUpozila()
    {
        return $this->upozila;
    }

    /**
     * @param mixed $upozila
     */
    public function setUpozila($upozila)
    {
        $this->upozila = $upozila;
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
    public function setAgent(Agent $agent)
    {
        $this->agent = $agent;
    }

    /**
     * @return MarkChart
     */
    public function getProduct(): MarkChart
    {
        return $this->product;
    }

    /**
     * @param MarkChart $product
     */
    public function setProduct(MarkChart $product)
    {
        $this->product = $product;
    }

    /**
     * @return float
     */
    public function getQuantity(): float
    {
        return $this->quantity;
    }

    /**
     * @param float $quantity
     */
    public function setQuantity(float $quantity)
    {
        $this->quantity = $quantity;
    }

    /**
     * @return float
     */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * @param float $amount
     */
    public function setAmount(float $amount)
    {
        $this->amount = $amount;
    }

    /**
     * @return \DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * @param \DateTime $created
     */
    public function setCreated($created)
    {
        $this->created = $created;
    }

    /**
     * @return \DateTime
     */
    public function getUpdated()
    {
        return $this->updated;
    }

    /**
     * @param \DateTime $updated
     */
    public function setUpdated($updated)
    {
        $this->updated = $updated;
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
    public function setMonth( $month)
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
     * @return DocumentUpload
     */
    public function getDocumentUpload()
    {
        return $this->documentUpload;
    }

    /**
     * @param mixed $documentUpload
     */
    public function setDocumentUpload($documentUpload)
    {
        $this->documentUpload = $documentUpload;
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
    public function setStatus(bool $status): void
    {
        $this->status = $status;
    }


}
