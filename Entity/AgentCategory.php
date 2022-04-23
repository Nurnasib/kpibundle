<?php

namespace Terminalbd\KpiBundle\Entity;

use App\Entity\Core\Agent;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;


/**
 * AgentCategory
 * @ORM\Table(name="kpi_agent_category")
 * @ORM\Entity(repositoryClass="Terminalbd\KpiBundle\Repository\AgentCategoryRepository")
 */
class AgentCategory

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
     * @var Agent
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\Agent", inversedBy="agentCategory")
     */
    private $agent;

    /**
     * @var AgentGradeStandard
     *
     * @ORM\ManyToOne(targetEntity="Terminalbd\KpiBundle\Entity\AgentGradeStandard", inversedBy="agentCategory")
     */
    private $gradeStandard;

    /**
     * @var float
     * @ORM\Column(name="quantity", type="float", nullable=true)
     */
    private $quantity;

    /**
     * @var float
     * @ORM\Column(name="average", type="float", nullable=true)
     */
    private $average;

    /**
     * @var DocumentUpload
     *
     * @ORM\ManyToOne(targetEntity="Terminalbd\KpiBundle\Entity\DocumentUpload" , inversedBy="agentCategory")
     * @ORM\JoinColumn(onDelete="cascade")
     */
    private $documentUpload;


    /**
     * @var string
     * @ORM\Column(name="month", type="string", nullable=true)
     */
    private $month;

    /**
     * @var date
     * @ORM\Column(type="date", nullable=true)
     */
    private $createdMonth;


    /**
     * @var string
     * @ORM\Column(name="year", type="string", nullable=true)
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
     * @var integer
     * @ORM\Column(type="integer", nullable=true)
     */
    private $monthCount;

    /**
     * @var float
     * @ORM\Column(type="float", nullable=true)
     */
    private $cumulativeQuantity;

    public function __construct()
    {
        $this->agent = new ArrayCollection();
        $this->gradeStandard = new ArrayCollection();
    }

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
     * @return Agent
     */
    public function getAgent()
    {
        return $this->agent;
    }

    /**
     * @param Agent $agent
     */
    public function setAgent($agent)
    {
        $this->agent = $agent;
    }

    /**
     * @return Agent
     */
    public function getGradeStandard()
    {
        return $this->gradeStandard;
    }

    /**
     * @param Agent $gradeStandard
     */
    public function setGradeStandard($gradeStandard)
    {
        $this->gradeStandard = $gradeStandard;
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

    /**
     * @return float
     */
    public function getAverage()
    {
        return $this->average;
    }

    /**
     * @param float $average
     */
    public function setAverage($average)
    {
        $this->average = $average;
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
    public function setMonth($month)
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
    public function setYear($year)
    {
        $this->year = $year;
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
    public function setCreatedAt($createdAt )
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @param \DateTime $updatedAt
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
    }


    /**
     * @return DocumentUpload
     */
    public function getDocumentUpload()
    {
        return $this->documentUpload;
    }

    /**
     * @param DocumentUpload $documentUpload
     */
    public function setDocumentUpload($documentUpload)
    {
        $this->documentUpload = $documentUpload;
    }

    /**
     * @return date
     */
    public function getCreatedMonth()
    {
        return $this->createdMonth;
    }

    /**
     * @param date $createdMonth
     */
    public function setCreatedMonth($createdMonth)
    {
        $this->createdMonth = $createdMonth;
    }

    /**
     * @return int
     */
    public function getMonthCount(): int
    {
        return $this->monthCount;
    }

    /**
     * @param int $monthCount
     */
    public function setMonthCount(int $monthCount): void
    {
        $this->monthCount = $monthCount;
    }

    /**
     * @return float
     */
    public function getCumulativeQuantity(): float
    {
        return $this->cumulativeQuantity;
    }

    /**
     * @param float $cumulativeQuantity
     */
    public function setCumulativeQuantity(float $cumulativeQuantity): void
    {
        $this->cumulativeQuantity = $cumulativeQuantity;
    }

    


    public function addAgent(Agent $agent): self
    {
        if (!$this->agent->contains($agent)) {
            $this->agent[] = $agent;
            $agent->setAgentCategory($this);
        }

        return $this;
    }

    public function removeAgent(Agent $agent): self
    {
        if ($this->agent->removeElement($agent)) {
            // set the owning side to null (unless already changed)
            if ($agent->getAgentCategory() === $this) {
                $agent->setAgentCategory(null);
            }
        }

        return $this;
    }

    public function addGradeStandard(AgentGradeStandard $gradeStandard): self
    {
        if (!$this->gradeStandard->contains($gradeStandard)) {
            $this->gradeStandard[] = $gradeStandard;
            $gradeStandard->setAgentCategory($this);
        }

        return $this;
    }

    public function removeGradeStandard(AgentGradeStandard $gradeStandard): self
    {
        if ($this->gradeStandard->removeElement($gradeStandard)) {
            // set the owning side to null (unless already changed)
            if ($gradeStandard->getAgentCategory() === $this) {
                $gradeStandard->setAgentCategory(null);
            }
        }

        return $this;
    }


}
