<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\InvoiceTemplateRepository")
 * @ORM\Table(name="kimai2_invoice_templates",
 *      uniqueConstraints={
 *          @ORM\UniqueConstraint(columns={"name"})
 *      }
 * )
 */
class InvoiceTemplate
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=60, nullable=false)
     * @Assert\NotBlank()
     * @Assert\Length(min=1, max=60)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="title", type="string", length=255, nullable=false)
     * @Assert\NotBlank()
     */
    private $title;

    /**
     * @var string
     *
     * @ORM\Column(name="company", type="string", length=255, nullable=false)
     * @Assert\NotBlank()
     */
    private $company;

    /**
     * @var string
     *
     * @ORM\Column(name="address", type="text", nullable=true)
     */
    private $address;

    /**
     * @var int
     *
     * @ORM\Column(name="due_days", type="integer", length=3, nullable=false)
     * @Assert\Range(min = 0, max = 999)
     */
    private $dueDays = 30;

    /**
     * @var float
     *
     * @ORM\Column(name="vat", type="float", nullable=false)
     * @Assert\Range(min = 0.0, max = 99.99)
     */
    private $vat = 0.00;

    /**
     * @var string
     *
     * @ORM\Column(name="calculator", type="string", length=20, nullable=false)
     * @Assert\NotBlank()
     */
    private $calculator = 'default';
    /**
     * @var string
     *
     * @ORM\Column(name="number_generator", type="string", length=20, nullable=false)
     * @Assert\NotBlank()
     */
    private $numberGenerator = 'default';

    /**
     * @var string
     *
     * @ORM\Column(name="renderer", type="string", length=20, nullable=false)
     * @Assert\NotBlank()
     */
    private $renderer = 'default';

    /**
     * @var string
     *
     * @ORM\Column(name="payment_terms", type="text", nullable=true)
     */
    private $paymentTerms;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setName(string $name): InvoiceTemplate
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): InvoiceTemplate
    {
        $this->title = $title;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): InvoiceTemplate
    {
        $this->address = $address;

        return $this;
    }

    public function getNumberGenerator(): string
    {
        return $this->numberGenerator;
    }

    public function setNumberGenerator(string $numberGenerator): InvoiceTemplate
    {
        $this->numberGenerator = $numberGenerator;

        return $this;
    }

    public function getDueDays(): int
    {
        return $this->dueDays;
    }

    public function setDueDays(int $dueDays): InvoiceTemplate
    {
        $this->dueDays = $dueDays;

        return $this;
    }

    public function getVat(): float
    {
        return $this->vat;
    }

    public function setVat(float $vat): InvoiceTemplate
    {
        $this->vat = $vat;

        return $this;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(string $company): InvoiceTemplate
    {
        $this->company = $company;

        return $this;
    }

    public function getRenderer(): string
    {
        return $this->renderer;
    }

    public function setRenderer(string $renderer): InvoiceTemplate
    {
        $this->renderer = $renderer;

        return $this;
    }

    public function getCalculator(): string
    {
        return $this->calculator;
    }

    public function setCalculator(string $calculator): InvoiceTemplate
    {
        $this->calculator = $calculator;

        return $this;
    }

    public function getPaymentTerms(): ?string
    {
        return $this->paymentTerms;
    }

    public function setPaymentTerms(?string $paymentTerms): InvoiceTemplate
    {
        $this->paymentTerms = $paymentTerms;

        return $this;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getName();
    }
}
