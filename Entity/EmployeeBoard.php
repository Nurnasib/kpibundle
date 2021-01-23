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
     * @var EmployeeSetup
     *
     * @ORM\ManyToOne(targetEntity="EmployeeSetup", inversedBy="employeeBoard" , cascade={"detach","merge"})
     * @ORM\JoinColumn(name="employeeSetup_id", referencedColumnName="id", nullable=true, onDelete="cascade")
     */
    private $employeeSetup;

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


}
