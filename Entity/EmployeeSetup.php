<?php

namespace Terminalbd\KpiBundle\Entity;

use App\Entity\Admin\Location;
use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;


/**
 * KpiSetup
 *
 * @ORM\Table(name="kpi_setup")
 * @ORM\Entity(repositoryClass="Terminalbd\KpiBundle\Repository\EmployeeSetupRepository")
 */
class EmployeeSetup
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
     * @ORM\ManyToOne(targetEntity="App\Entity\User" , inversedBy="kpiSetup")
     */
    private $employee;


    /**
     * @var SetupMatrix
     *
     * @ORM\OneToMany(targetEntity="SetupMatrix" , mappedBy="employeeSetup")
     */
    private $setupMatrix;


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
     * @return User
     */
    public function getEmployee()
    {
        return $this->employee;
    }

    /**
     * @param User $employee
     */
    public function setEmployee(User $employee)
    {
        $this->employee = $employee;
    }

    /**
     * @return SetupMatrix
     */
    public function getSetupMatrix()
    {
        return $this->setupMatrix;
    }


}
