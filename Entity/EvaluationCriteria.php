<?php

namespace Terminalbd\KpiBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * AgentOrder
 *
 * @ORM\Table(name="kpi_evaluation_criteria")
 * @ORM\Entity(repositoryClass="Terminalbd\KpiBundle\Repository\EvaluationCriteriaRepository")
 */
class EvaluationCriteria
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
     * @var string
     * @ORM\Column(type="string", nullable=true)
     */
    private $name;
    /**
     * @var string
     * @ORM\Column(name="type", type="string", nullable=true)
     */
    private $type;

    /**
     * @var float
     * @ORM\Column(type="float")
     */
    private $reportTarget = 0;

    /**
     * @var float
     * @ORM\Column(type="float")
     */
    private $marksTarget = 0;
    /**
     * @var \DateTime
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $createdAt;

    /**
     * @var string
     * @ORM\Column(name="slug", type="string")
     */
    private $slug;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }


    /**
     * @return float
     */
    public function getReportTarget()
    {
        return $this->reportTarget;
    }

    /**
     * @param float $reportTarget
     */
    public function setReportTarget($reportTarget)
    {
        $this->reportTarget = $reportTarget;
    }

    /**
     * @return float
     */
    public function getMarksTarget()
    {
        return $this->marksTarget;
    }

    /**
     * @param float $marksTarget
     */
    public function setMarksTarget($marksTarget)
    {
        $this->marksTarget = $marksTarget;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTime $createdAt
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
    }

    /**
     * @param string $slug
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;
    }


}
