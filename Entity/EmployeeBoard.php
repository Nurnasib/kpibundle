<?php

namespace Terminalbd\KpiBundle\Entity;

use App\Entity\Admin\Location;
use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;


/**
 * KpiSetup
 *
 * @ORM\Table(name="kpi_employee_board")
 * @ORM\Entity(repositoryClass="Terminalbd\KpiBundle\Repository\EmployeeBoardRepository")
 */
class EmployeeBoard
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
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\User" , inversedBy="employeeBoard")
     * @ORM\JoinColumn(name="employee_id", referencedColumnName="id", nullable=true)
     */
    private $employee;

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
     * @var string
     * @ORM\Column(name="process", type="string", nullable=true)
     */
     private $process = "created";

    /**
     * @var \DateTime
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(name="created", type="datetime")
     */
    private $created;

    /**
     * @var \DateTime
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(name="updated", type="datetime")
     */
    private $updated;

    /**
     * @var EmployeeBoardAttribute
     *
     * @ORM\OneToMany(targetEntity="Terminalbd\KpiBundle\Entity\EmployeeBoardAttribute", mappedBy="employeeBoard")
     */
    private $employeeBoardAttributes;

    /**
     * @var User
     * @ORM\ManyToOne(targetEntity="App\Entity\User" , inversedBy="employeeBoard")
     */
    private $createdBy;

    /**
     * @var integer
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    private $status;

    /**
     * @var User
     * @ORM\ManyToOne(targetEntity="App\Entity\User" , inversedBy="employeeBoard")
     */
    private $approvedBy;

    /**
     * @var float
     * @ORM\Column(type="float", nullable=true)
     */
    private $actualMark;

    /**
     * @var float
     * @ORM\Column(type="float", nullable=true)
     */
    private $obtainMark;

    /**
     * @var float
     * @ORM\Column(type="float", nullable=true)
     */
    private $selfMark;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     */
    private $grade;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     */
    private $selfGrade;

    /**
     * @var string
     * @ORM\Column(type="text", nullable=true)
     */
    private $district;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\Setting")
     */
    protected $reportMode;

    /**
     * @var boolean
     *
     * @ORM\Column(type="boolean")
     */
    private $isReverse = false;

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
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param int $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }


    /**
     * @return User
     */
    public function getEmployee()
    {
        return $this->employee;
    }

    /**
     * @param User $employee
     */
    public function setEmployee($employee)
    {
        $this->employee = $employee;
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
    public function setMonth(string $month)
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
    public function setYear(string $year)
    {
        $this->year = $year;
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
    public function getProcess()
    {
        return $this->process;
    }

    /**
     * @param string $process
     */
    public function setProcess(string $process)
    {
        $this->process = $process;
    }

    /**
     * @return EmployeeBoardAttribute
     */
    public function getEmployeeBoardAttributes()
    {
        return $this->employeeBoardAttributes;
    }

    /**
     * @return User
     */
    public function getCreatedBy()
    {
        return $this->createdBy;
    }

    /**
     * @param User $createdBy
     */
    public function setCreatedBy($createdBy)
    {
        $this->createdBy = $createdBy;
    }

    /**
     * @return User
     */
    public function getApprovedBy()
    {
        return $this->approvedBy;
    }

    /**
     * @param User $approvedBy
     */
    public function setApprovedBy($approvedBy): void
    {
        $this->approvedBy = $approvedBy;
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
    public function setActualMark($actualMark)
    {
        $this->actualMark = $actualMark;
    }

    /**
     * @return float
     */
    public function getObtainMark()
    {
        return $this->obtainMark;
    }

    /**
     * @param float $obtainMark
     */
    public function setObtainMark($obtainMark)
    {
        $this->obtainMark = $obtainMark;
    }

    /**
     * @return string
     */
    public function getGrade()
    {
        return $this->grade;
    }

    /**
     * @param string $grade
     */
    public function setGrade($grade)
    {
        $this->grade = $grade;
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
     * @return string
     */
    public function getSelfGrade()
    {
        return $this->selfGrade;
    }

    /**
     * @param string $selfGrade
     */
    public function setSelfGrade(string $selfGrade): void
    {
        $this->selfGrade = $selfGrade;
    }

    /**
     * @return string
     */
    public function getDistrict(): string
    {
        return $this->district;
    }

    /**
     * @param string $district
     */
    public function setDistrict($district): void
    {
        $this->district = $district;
    }

    /**
     * @return mixed
     */
    public function getReportMode()
    {
        return $this->reportMode;
    }

    /**
     * @param mixed $reportMode
     */
    public function setReportMode($reportMode): void
    {
        $this->reportMode = $reportMode;
    }

    /**
     * @return bool
     */
    public function isReverse(): bool
    {
        return $this->isReverse;
    }

    /**
     * @param bool $isReverse
     */
    public function setIsReverse(bool $isReverse): void
    {
        $this->isReverse = $isReverse;
    }


}
