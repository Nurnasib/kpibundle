<?php

namespace Terminalbd\KpiBundle\Entity;

use App\Entity\User;
use App\Entity\Core\Setting;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;



/**
 * AgentCategory
 * @ORM\Table(name="kpi_employee_report_format_history")
 * @ORM\Entity(repositoryClass="Terminalbd\KpiBundle\Repository\EmployeeReportFormatHistoryRepository")
 */
class EmployeeReportFormatHistory

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
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     * @ORM\JoinColumn(referencedColumnName="id")
     */
    private $employee;


    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     * @ORM\JoinColumn(referencedColumnName="id")
     */
    private $updatedBy;

    /**
     * @var Setting
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\Setting")
     * @ORM\JoinColumn(referencedColumnName="id")
     */
    private $reportFormat;


    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     */
    private $month;


    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     */
    private $year;


    /**
     * @var \DateTime
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $createdAt;

    /**
     * @var \DateTime
     * @ORM\Column(name="updated_at", type="datetime", nullable = true)
     */
    private $updatedAt;

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
     * @return User
     */
    public function getEmployee(): User
    {
        return $this->employee;
    }

    /**
     * @param User $employee
     */
    public function setEmployee(User $employee): void
    {
        $this->employee = $employee;
    }

    /**
     * @return User
     */
    public function getUpdatedBy(): User
    {
        return $this->updatedBy;
    }

    /**
     * @param User $updatedBy
     */
    public function setUpdatedBy(User $updatedBy): void
    {
        $this->updatedBy = $updatedBy;
    }

    /**
     * @return Setting
     */
    public function getReportFormat(): Setting
    {
        return $this->reportFormat;
    }

    /**
     * @param Setting $reportFormat
     */
    public function setReportFormat(Setting $reportFormat): void
    {
        $this->reportFormat = $reportFormat;
    }


    /**
     * @return string
     */
    public function getMonth(): string
    {
        return $this->month;
    }

    /**
     * @param string $month
     */
    public function setMonth(string $month): void
    {
        $this->month = $month;
    }

    /**
     * @return string
     */
    public function getYear(): string
    {
        return $this->year;
    }

    /**
     * @param string $year
     */
    public function setYear(string $year): void
    {
        $this->year = $year;
    }


    /**
     * @return \DateTime
     */
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTime $createdAt
     */
    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return \DateTime
     */
    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    /**
     * @param \DateTime $updatedAt
     */
    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }


}
