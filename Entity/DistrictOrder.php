<?php

namespace Terminalbd\KpiBundle\Entity;

use App\Entity\Admin\Location;
use App\Entity\Core\Agent;
use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;


/**
 * DistrictOrder
 *
 * @ORM\Table(name="kpi_district_order")
 * @ORM\Entity(repositoryClass="Terminalbd\KpiBundle\Repository\DistrictOrderRepository")
 */
class DistrictOrder

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
     * @ORM\ManyToOne(targetEntity="App\Entity\Admin\Location")
     */
    protected $district;

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
     * @ORM\Column(type="float", nullable=true)
     */
    private $cumulativeQuantity;


    /**
     * @var float
     * @ORM\Column(type="float", nullable=true)
     */
    private $cumulativeTargetQuantity;


    /**
     * @var float
     * @ORM\Column(type="float", nullable=true)
     */
    private $targetQuantity;

    /**
     * @var float
     * @ORM\Column(type="float", nullable=true)
     */
    private $salesGrouthPreviousQuantity;

    /**
     * @var float
     * @ORM\Column(type="float", nullable=true)
     */
    private $salesGrouthCurrentQuantity;

    /**
     * @var float
     * @ORM\Column(type="float", nullable=true)
     */
    private $salesMarkPercentage;

    /**
     * @var float
     * @ORM\Column(type="float", nullable=true)
     */
    private $salesMark;


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
    public function getQuantity()
    {
        return $this->quantity;
    }

    /**
     * @param float $quantity
     */
    public function setQuantity($quantity)
    {
        $this->quantity = $quantity;
    }

    /**
     * @return float
     */
    public function getCumulativeQuantity(): float
    {
        return $this->cumulativeQuantity;
    }

    /**
     * @param float $cumulativeQuantity
     */
    public function setCumulativeQuantity(float $cumulativeQuantity): void
    {
        $this->cumulativeQuantity = $cumulativeQuantity;
    }

    /**
     * @return float
     */
    public function getCumulativeTargetQuantity(): float
    {
        return $this->cumulativeTargetQuantity;
    }

    /**
     * @param float $cumulativeTargetQuantity
     */
    public function setCumulativeTargetQuantity(float $cumulativeTargetQuantity): void
    {
        $this->cumulativeTargetQuantity = $cumulativeTargetQuantity;
    }


    /**
     * @return float
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param float $amount
     */
    public function setAmount($amount)
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

    /**
     * @return float
     */
    public function getTargetQuantity()
    {
        return $this->targetQuantity;
    }

    /**
     * @param float $targetQuantity
     */
    public function setTargetQuantity($targetQuantity): void
    {
        $this->targetQuantity = $targetQuantity;
    }

    /**
     * @return float
     */
    public function getSalesGrouthPreviousQuantity()
    {
        return $this->salesGrouthPreviousQuantity;
    }

    /**
     * @param float $salesGrouthPreviousQuantity
     */
    public function setSalesGrouthPreviousQuantity($salesGrouthPreviousQuantity): void
    {
        $this->salesGrouthPreviousQuantity = $salesGrouthPreviousQuantity;
    }

    /**
     * @return float
     */
    public function getSalesGrouthCurrentQuantity()
    {
        return $this->salesGrouthCurrentQuantity;
    }

    /**
     * @param float $salesGrouthCurrentQuantity
     */
    public function setSalesGrouthCurrentQuantity($salesGrouthCurrentQuantity): void
    {
        $this->salesGrouthCurrentQuantity = $salesGrouthCurrentQuantity;
    }

    /**
     * @return float
     */
    public function getSalesMarkPercentage()
    {
        return $this->salesMarkPercentage;
    }

    /**
     * @param float $salesMarkPercentage
     */
    public function setSalesMarkPercentage($salesMarkPercentage)
    {
        $this->salesMarkPercentage = $salesMarkPercentage;
    }

    /**
     * @return float
     */
    public function getSalesMark()
    {
        return $this->salesMark;
    }

    /**
     * @param float $salesMark
     */
    public function setSalesMark($salesMark)
    {
        $this->salesMark = $salesMark;
    }


}
