<?php

namespace Terminalbd\KpiBundle\Entity;

use App\Entity\Admin\Location;
use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;


/**
 * EmployeeBoardAttribute
 *
 * @ORM\Table(name="kpi_board_attribute")
 * @ORM\Entity(repositoryClass="Terminalbd\KpiBundle\Repository\EmployeeBoardAttributeRepository")
 */
class EmployeeBoardAttribute
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
     * @ORM\ManyToOne(targetEntity="MarkChart")
     */
    private $parameter;



    /**
     * @var MarkChart
     *
     * @ORM\ManyToOne(targetEntity="MarkChart")
     */
    private $activity;



    /**
     * @var MarkChart
     *
     * @ORM\ManyToOne(targetEntity="MarkChart")
     */
    private $attribute;

    /**
     * @var MarkChart
     *
     * @ORM\ManyToOne(targetEntity="MarkChart")
     */
    private $markDistribution;



    /**
     * @var float
     * @ORM\Column(name="targetAmount", type="float", nullable=true)
     */
    private $targetAmount;


    /**
     * @var float
     * @ORM\Column(name="targetAchievement", type="float", nullable=true)
     */
    private $targetAchievement;


    /**
     * @var float
     * @ORM\Column(name="actualMark", type="float", nullable=true)
     */
    private $actualMark;

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
    public function getAttribute()
    {
        return $this->attribute;
    }

    /**
     * @param MarkChart $attribute
     */
    public function setAttribute(MarkChart $attribute)
    {
        $this->attribute = $attribute;
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
    public function setMark($mark)
    {
        $this->mark = $mark;
    }



    /**
     * @return float
     */
    public function getTargetAmount()
    {
        return $this->targetAmount;
    }

    /**
     * @param float $targetAmount
     */
    public function setTargetAmount(float $targetAmount)
    {
        $this->targetAmount = $targetAmount;
    }

    /**
     * @return float
     */
    public function getTargetAchievement()
    {
        return $this->targetAchievement;
    }

    /**
     * @param float $targetAchievement
     */
    public function setTargetAchievement(float $targetAchievement)
    {
        $this->targetAchievement = $targetAchievement;
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
    public function getParameter()
    {
        return $this->parameter;
    }

    /**
     * @param MarkChart $parameter
     */
    public function setParameter(MarkChart $parameter)
    {
        $this->parameter = $parameter;
    }

    /**
     * @return MarkChart
     */
    public function getActivity()
    {
        return $this->activity;
    }

    /**
     * @param MarkChart $activity
     */
    public function setActivity(MarkChart $activity)
    {
        $this->activity = $activity;
    }

    /**
     * @return float
     */
    public function getActualMark()
    {
        return $this->actualMark;
    }

    /**
     * @param float $actualMark
     */
    public function setActualMark(float $actualMark)
    {
        $this->actualMark = $actualMark;
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
    public function setMarkDistribution($markDistribution)
    {
        $this->markDistribution = $markDistribution;
    }


}
