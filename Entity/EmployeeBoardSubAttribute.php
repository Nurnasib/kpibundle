<?php

namespace Terminalbd\KpiBundle\Entity;

use App\Entity\Admin\Location;
use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;


/**
 * EmployeeBoardSubAttribute
 *
 * @ORM\Table(name="kpi_board_subattribute")
 * @ORM\Entity(repositoryClass="Terminalbd\KpiBundle\Repository\EmployeeBoardSubAttributeRepository")
 */
class EmployeeBoardSubAttribute
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
     * @var EmployeeBoard
     *
     * @ORM\ManyToOne(targetEntity="EmployeeBoard", inversedBy="employeeBoardAttribute" , cascade={"detach","merge"})
     * @ORM\JoinColumn(name="employeeBoard_id", referencedColumnName="id", nullable=true, onDelete="cascade")
     */
    private $employeeBoard;



    /**
     * @var MarkChart
     *
     * @ORM\ManyToOne(targetEntity="MarkChart" , inversedBy="employeeSetupAttribute")
     */
    private $markDistribution;


    /**
     * @var float
     * @ORM\Column(name="salesTargetAmount", type="float", nullable=true)
     */
    private $salesTargetAmount;


    /**
     * @var float
     * @ORM\Column(name="salesAmount", type="float", nullable=true)
     */
    private $salesAmount;

    /**
     * @var float
     * @ORM\Column(type="float", nullable=true)
     */
    private $targetQuantity;

    /**
     * @var float
     * @ORM\Column(type="float", nullable=true)
     */
    private $salesQuantity;


    /**
     * @var float
     * @ORM\Column(name="mark", type="float", nullable=true)
     */
    private $mark;

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
     * @return float
     */
    public function getSalesAmount()
    {
        return $this->salesAmount;
    }

    /**
     * @param float $salesAmount
     */
    public function setSalesAmount(float $salesAmount)
    {
        $this->salesAmount = $salesAmount;
    }

    /**
     * @return EmployeeBoard
     */
    public function getEmployeeBoard()
    {
        return $this->employeeBoard;
    }

    /**
     * @param EmployeeBoard $employeeBoard
     */
    public function setEmployeeBoard(EmployeeBoard $employeeBoard)
    {
        $this->employeeBoard = $employeeBoard;
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
    public function getSalesTargetAmount()
    {
        return $this->salesTargetAmount;
    }

    /**
     * @param float $salesTargetAmount
     */
    public function setSalesTargetAmount(float $salesTargetAmount)
    {
        $this->salesTargetAmount = $salesTargetAmount;
    }

    /**
     * @return float
     */
    public function getMark()
    {
        return $this->mark;
    }

    /**
     * @param float $mark
     */
    public function setMark(float $mark)
    {
        $this->mark = $mark;
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
    public function setTargetQuantity($targetQuantity)
    {
        $this->targetQuantity = $targetQuantity;
    }

    /**
     * @return float
     */
    public function getSalesQuantity()
    {
        return $this->salesQuantity;
    }

    /**
     * @param float $salesQuantity
     */
    public function setSalesQuantity($salesQuantity)
    {
        $this->salesQuantity = $salesQuantity;
    }




}
