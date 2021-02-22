<?php

namespace Terminalbd\KpiBundle\Entity;

use App\Entity\Admin\Location;
use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;


/**
 * setupMatrix
 *
 * @ORM\Table(name="kpi_setup_matrix")
 * @ORM\Entity(repositoryClass="Terminalbd\KpiBundle\Repository\SetupMatrixRepository")
 */
class SetupMatrix
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
     * @var EmployeeSetup
     *
     * @ORM\ManyToOne(targetEntity="EmployeeSetup" , inversedBy="setupMatrix")
     * @ORM\JoinColumn(onDelete="CASCADE")
     */
    private $employeeSetup;


    /**
     * @var MarkChart
     *
     * @ORM\ManyToOne(targetEntity="MarkChart" , inversedBy="setupMatrix")
     */
    private $markChart;

     /**
     * @var LocationSalesTarget
     *
     * @ORM\ManyToOne(targetEntity="LocationSalesTarget" , inversedBy="setupMatrix")
     */
    private $sales;

    /**
     * @var MarkChart
     *
     * @ORM\ManyToOne(targetEntity="MarkChart" , inversedBy="setupMatrix")
     */
    private $markDistribution;

    /**
     * @var Location
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Admin\Location" , inversedBy="setupMatrix")
     */
    private $upozila;

    /**
     * @var Location
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Admin\Location" , inversedBy="setupMatrix")
     */
    private $district;

    /**
     * @var Location
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Admin\Location" , inversedBy="setupMatrix")
     */
    private $regional;


    /**
     * @var Location
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Admin\Location" , inversedBy="setupMatrix")
     */
    private $zonal;
    

    /**
     * @var float
     *
     * @ORM\Column(type="float",nullable=true)
     */
    private $amount;


    /**
     * @var float
     *
     * @ORM\Column(type="float",nullable=true)
     */
    private $quantity;


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
     * @return EmployeeSetup
     */
    public function getEmployeeSetup()
    {
        return $this->employeeSetup;
    }

    /**
     * @param EmployeeSetup $employeeSetup
     */
    public function setEmployeeSetup(EmployeeSetup $employeeSetup)
    {
        $this->employeeSetup = $employeeSetup;
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
    public function getZonal()
    {
        return $this->zonal;
    }

    /**
     * @param Location $zonal
     */
    public function setZonal(Location $zonal)
    {
        $this->zonal = $zonal;
    }

    /**
     * @return MarkChart
     */
    public function getMarkChart()
    {
        return $this->markChart;
    }

    /**
     * @param MarkChart $markChart
     */
    public function setMarkChart(MarkChart $markChart)
    {
        $this->markChart = $markChart;
    }

    /**
     * @return LocationSalesTarget
     */
    public function getSales()
    {
        return $this->sales;
    }

    /**
     * @param LocationSalesTarget $sales
     */
    public function setSales(LocationSalesTarget $sales)
    {
        $this->sales = $sales;
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
