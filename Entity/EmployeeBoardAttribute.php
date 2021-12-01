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
     * @ORM\ManyToOne(targetEntity="EmployeeBoard", inversedBy="employeeBoardAttributes" , cascade={"detach","merge"})
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
     * @var integer
     * @ORM\Column(type="integer", nullable=true)
     */
    private $targetReport;

    /**
     * @var integer
     * @ORM\Column(type="integer", nullable=true)
     */
    private $achieveReport;

    /**
     * @var integer
     * @ORM\Column(type="integer", nullable=true)
     */
    private $targetMark;


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
     * @var float
     * @ORM\Column(type="float", nullable=true)
     */
    private $selfMark;

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
     * @return EmployeeBoard
     */
    public function getEmployeeBoard(): EmployeeBoard
    {
        return $this->employeeBoard;
    }

    /**
     * @param EmployeeBoard $employeeBoard
     */
    public function setEmployeeBoard(EmployeeBoard $employeeBoard): void
    {
        $this->employeeBoard = $employeeBoard;
    }

    /**
     * @return MarkChart
     */
    public function getParameter(): MarkChart
    {
        return $this->parameter;
    }

    /**
     * @param MarkChart $parameter
     */
    public function setParameter(MarkChart $parameter): void
    {
        $this->parameter = $parameter;
    }

    /**
     * @return MarkChart
     */
    public function getActivity(): MarkChart
    {
        return $this->activity;
    }

    /**
     * @param MarkChart $activity
     */
    public function setActivity(MarkChart $activity): void
    {
        $this->activity = $activity;
    }

    /**
     * @return MarkChart
     */
    public function getAttribute(): MarkChart
    {
        return $this->attribute;
    }

    /**
     * @param MarkChart $attribute
     */
    public function setAttribute(MarkChart $attribute): void
    {
        $this->attribute = $attribute;
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
    public function setMarkDistribution(MarkChart $markDistribution): void
    {
        $this->markDistribution = $markDistribution;
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
    public function setTargetAmount(float $targetAmount): void
    {
        $this->targetAmount = $targetAmount;
    }

    /**
     * @return int
     */
    public function getTargetReport()
    {
        return $this->targetReport;
    }

    /**
     * @param int $targetReport
     */
    public function setTargetReport(int $targetReport): void
    {
        $this->targetReport = $targetReport;
    }

    /**
     * @return int
     */
    public function getAchieveReport()
    {
        return $this->achieveReport;
    }

    /**
     * @param int $achieveReport
     */
    public function setAchieveReport(int $achieveReport): void
    {
        $this->achieveReport = $achieveReport;
    }

    /**
     * @return int
     */
    public function getTargetMark()
    {
        return $this->targetMark;
    }

    /**
     * @param int $targetMark
     */
    public function setTargetMark(int $targetMark): void
    {
        $this->targetMark = $targetMark;
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
    public function setTargetAchievement(float $targetAchievement): void
    {
        $this->targetAchievement = $targetAchievement;
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
    public function setActualMark(float $actualMark): void
    {
        $this->actualMark = $actualMark;
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
    public function setMark(float $mark): void
    {
        $this->mark = $mark;
    }

    /**
     * @return float
     */
    public function getSelfMark()
    {
        return $this->selfMark;
    }

    /**
     * @param float $selfMark
     */
    public function setSelfMark(float $selfMark): void
    {
        $this->selfMark = $selfMark;
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
