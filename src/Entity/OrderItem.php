<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get; // Se vuoi endpoint diretti per OrderItem
use App\Repository\OrderItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
// Gli OrderItem sono tipicamente gestiti come parte di un Order (risorsa subalterna o embedded).
// Potresti non volere endpoint API diretti per /api/order_items.
// Se non li vuoi, rimuovi l'attributo #[ApiResource] o limita le operazioni.
#[ApiResource(
    operations: [
        // new Get(normalizationContext: ['groups' => ['orderitem:read', 'product:read']]), // Esempio se vuoi un GET singolo
    ],
    normalizationContext: ['groups' => ['orderitem:read']], // Gruppo di default per la lettura (se esposto)
    denormalizationContext: ['groups' => ['orderitem:write']] // Gruppo di default per la scrittura (se esposto)
)]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['order:read', 'order:item:read'])] // Esposto quando si legge un Order
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'orderItems')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    // Non esporre 'relatedOrder' nei gruppi di scrittura 'order:write' o 'orderitem:write'
    // perché viene impostato automaticamente dalla relazione bidirezionale in Order::addOrderItem.
    #[Groups(['orderitem:read'])] // Solo per lettura dell'item, se necessario
    private ?Order $relatedOrder = null;

    #[ORM\ManyToOne(targetEntity: Product::class)] // Non c'è inversedBy qui se non l'hai aggiunto in Product
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: "Il prodotto per l'item dell'ordine non può essere vuoto.")]
    #[Groups(['order:read', 'order:item:read', 'order:write', 'orderitem:read', 'orderitem:write'])] // Permetti di specificare il prodotto in scrittura
    private ?Product $product = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: "La quantità per l'item non può essere vuota.")]
    #[Assert\Positive(message: "La quantità deve essere un numero intero positivo.")]
    #[Groups(['order:read', 'order:item:read', 'order:write', 'orderitem:read', 'orderitem:write'])]
    private ?int $quantity = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: "Il prezzo di acquisto per l'item non può essere vuoto.")]
    #[Assert\PositiveOrZero(message: "Il prezzo di acquisto deve essere zero o positivo.")]
    #[Groups(['order:read', 'order:item:read', 'order:write', 'orderitem:read', 'orderitem:write'])]
    private ?string $priceAtPurchase = null; // Prezzo del prodotto al momento dell'acquisto

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

    /**
     * Calcola il subtotale per questo item.
     * Esposto nei gruppi di lettura per comodità.
     */
    #[Groups(['order:read', 'order:item:read'])]
    public function getSubtotal(): ?float
    {
        if ($this->priceAtPurchase === null || $this->quantity === null) {
            return null;
        }
        // Assicura che la moltiplicazione sia fatta con numeri float
        return round((float) $this->priceAtPurchase * $this->quantity, 2);
    }
}
