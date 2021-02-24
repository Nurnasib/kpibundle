<?php

namespace Terminalbd\KpiBundle\Entity;

use App\Entity\Admin\Location;
use Doctrine\ORM\Mapping as ORM;


/**
 * LocationSalesTarget
 *
 * @ORM\Table(name="kpi_location_sales_target")
 * @ORM\Entity(repositoryClass="Terminalbd\KpiBundle\Repository\LocationSalesTargetRepository")
 */
class LocationSalesTarget
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
     * @var MarkChart
     *
     * @ORM\ManyToOne(targetEntity="MarkChart")
     */
    private $markDistribution;


    /**
     * @var Location
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Admin\Location" ,inversedBy="salesTarget")
     */
    private $upozila;


     /**
     * @var Location
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Admin\Location")
     */
    private $district;


      /**
     * @var Location
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Admin\Location")
     */
    private $regional;


       /**
     * @var Location
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Admin\Location")
     */
    private $zone;


     /**
     * @var float
     * @ORM\Column(name="amount", type="float", nullable=true)
     */
    private $amount;


     /**
     * @var float
     * @ORM\Column(name="quantity", type="float", nullable=true)
     */
    private $quantity;


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
     * @return Location
     */
    public function getDistrict()
    {
        return $this->district;
    }

    /**
     * @param Location $district
     */
    public function setDistrict(Location $district)
    {
        $this->district = $district;
    }

    /**
     * @return Location
     */
    public function getRegional()
    {
        return $this->regional;
    }

    /**
     * @param Location $regional
     */
    public function setRegional(Location $regional)
    {
        $this->regional = $regional;
    }

    /**
     * @return Location
     */
    public function getZone()
    {
        return $this->zone;
    }

    /**
     * @param Location $zone
     */
    public function setZone(Location $zone)
    {
        $this->zone = $zone;
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
    public function setAmount(float $amount)
    {
        $this->amount = $amount;
    }

    /**
     * @return MarkChart
     */
    public function getMarkDistribution()
    {
        return $this->markDistribution;
    }

    /**
     * @param MarkChart $markDistribution
     */
    public function setMarkDistribution(MarkChart $markDistribution)
    {
        $this->markDistribution = $markDistribution;
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




}
