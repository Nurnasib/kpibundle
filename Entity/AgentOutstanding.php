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
 * @ORM\Table(name="kpi_agent_outstanding")
 * @ORM\Entity(repositoryClass="Terminalbd\KpiBundle\Repository\AgentOrderRepository")
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
     * @var Location
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Admin\Location" , inversedBy="kpiSetup")
     */
    private $upozila;


    /**
     * @var Agent
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\Agent" , inversedBy="kpiSetup")
     */
    private $agent;


    /**
     * @var float
     * @ORM\Column(name="outstanding", type="float", nullable=true)
     */
    private $outstanding;


    /**
     * @var float
     * @ORM\Column(name="actualAmount", type="float", nullable=true)
     */
    private $actualAmount;


    /**
     * @var \DateTime
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(name="created", type="datetime")
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
     * @return Location
     */
    public function getUpozila()
    {
        return $this->upozila;
    }

    /**
     * @param Location $upozila
     */
    public function setUpozila(Location $upozila)
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


}
