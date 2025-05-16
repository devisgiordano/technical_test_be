<?php
// src/Entity/OrderItem.php

namespace App\Entity;

use App\Repository\OrderItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'orderItems')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Order $relatedOrder = null;

    // Rimuoviamo cascade: ['persist'] se aggiunto precedentemente, lo gestiamo nel controller
    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: "Il prodotto per l'item dell'ordine non può essere vuoto.")]
    private ?Product $product = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: "La quantità per l'item non può essere vuota.")]
    #[Assert\Positive(message: "La quantità deve essere un numero intero positivo.")]
    private ?int $quantity = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: "Il prezzo di acquisto per l'item non può essere vuoto.")]
    #[Assert\PositiveOrZero(message: "Il prezzo di acquisto deve essere zero o positivo.")]
    private ?string $priceAtPurchase = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRelatedOrder(): ?Order
    {
        return $this->relatedOrder;
    }

    public function setRelatedOrder(?Order $relatedOrder): static
    {
        $this->relatedOrder = $relatedOrder;
        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;
        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getPriceAtPurchase(): ?string
    {
        return $this->priceAtPurchase;
    }

    public function setPriceAtPurchase(string $priceAtPurchase): static
    {
        $this->priceAtPurchase = $priceAtPurchase;
        return $this;
    }

    // Metodo per calcolare il subtotale, utile per la serializzazione
    public function getSubtotal(): ?float
    {
        if ($this->priceAtPurchase === null || $this->quantity === null) {
            return null;
        }
        return round((float) $this->priceAtPurchase * $this->quantity, 2);
    }
}
