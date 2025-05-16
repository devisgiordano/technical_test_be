<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter; // Per ricerche testuali
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;   // Per filtrare per data
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;  // Per ordinare i risultati
use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')] // Usa i backtick se 'order' è una parola chiave SQL comune
#[ApiResource(
    operations: [

        // GET /api/orders/{id}
        new Get(normalizationContext: ['groups' => ['order:read', 'order:item:read', 'product:read']]), // Include dettagli degli item e dei prodotti

        // GET /api/orders
        new GetCollection(normalizationContext: ['groups' => ['order:list:read']]), // Lista più snella

        // POST /api/orders
        new Post(denormalizationContext: ['groups' => ['order:write']]),

        // PUT /api/orders/{id}
        new Put(denormalizationContext: ['groups' => ['order:write']]),

        new Delete(
            // security: "is_granted('ROLE_ADMIN') or object.getOwner() == user" // Esempio di sicurezza
        )
    ],
    normalizationContext: ['groups' => ['order:read']], // Default per GET singolo
    denormalizationContext: ['groups' => ['order:write']],
    paginationItemsPerPage: 10,
    order: ['orderDate' => 'DESC'] // Ordina di default per data decrescente
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'orderNumber' => 'exact',
    'customerName' => 'partial', // Cerca parzialmente
    'description' => 'partial',
    'status' => 'exact'
])]
#[ApiFilter(DateFilter::class, properties: ['orderDate'])] // Permette filtri come orderDate[before]=... e orderDate[after]=...
#[ApiFilter(OrderFilter::class, properties: ['id', 'orderNumber', 'customerName', 'orderDate', 'status', 'totalAmount'], arguments: ['orderParameterName' => 'order'])]
#[ORM\HasLifecycleCallbacks] // Per calcolare totalAmount automaticamente
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['order:read', 'order:list:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank(message: "Il numero d'ordine non può essere vuoto.")]
    #[Groups(['order:read', 'order:list:read', 'order:write'])]
    private ?string $orderNumber = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Il nome del cliente non può essere vuoto.")]
    #[Groups(['order:read', 'order:list:read', 'order:write'])]
    private ?string $customerName = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull]
    #[Groups(['order:read', 'order:list:read', 'order:write'])]
    private ?\DateTimeImmutable $orderDate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['order:read', 'order:write'])]
    private ?string $description = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Choice(
        choices: ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'],
        message: "Scegli uno stato valido: Pending, Processing, Shipped, Delivered, Cancelled."
    )]
    #[Groups(['order:read', 'order:list:read', 'order:write'])]
    private ?string $status = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Groups(['order:read', 'order:list:read'])] // Calcolato, quindi solo in lettura
    private ?string $totalAmount = null;

    /**
     * @var Collection<int, OrderItem>
     */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'relatedOrder', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Assert\Valid] // Valida gli oggetti OrderItem annidati
    #[Assert\Count(min: 1, minMessage: "Un ordine deve contenere almeno un prodotto.")]
    #[Groups(['order:read', 'order:item:read', 'order:write'])]
    private Collection $orderItems;

    public function __construct()
    {
        $this->orderItems = new ArrayCollection();
        $this->orderDate = new \DateTimeImmutable(); // Data di default alla creazione
        $this->status = 'Pending';                   // Stato di default
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderNumber(): ?string
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(string $orderNumber): static
    {
        $this->orderNumber = $orderNumber;
        return $this;
    }

    public function getCustomerName(): ?string
    {
        return $this->customerName;
    }

    public function setCustomerName(string $customerName): static
    {
        $this->customerName = $customerName;
        return $this;
    }

    public function getOrderDate(): ?\DateTimeImmutable
    {
        return $this->orderDate;
    }

    public function setOrderDate(\DateTimeImmutable $orderDate): static
    {
        $this->orderDate = $orderDate;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getTotalAmount(): ?string
    {
        return $this->totalAmount;
    }

    // setTotalAmount non dovrebbe essere pubblico se calcolato automaticamente
    // public function setTotalAmount(?string $totalAmount): static
    // {
    //     $this->totalAmount = $totalAmount;
    //     return $this;
    // }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getOrderItems(): Collection
    {
        return $this->orderItems;
    }

    public function addOrderItem(OrderItem $orderItem): static
    {
        if (!$this->orderItems->contains($orderItem)) {
            $this->orderItems->add($orderItem);
            $orderItem->setRelatedOrder($this);
        }
        $this->updateTotalAmount(); // Aggiorna il totale quando un item è aggiunto
        return $this;
    }

    public function removeOrderItem(OrderItem $orderItem): static
    {
        if ($this->orderItems->removeElement($orderItem)) {
            // set the owning side to null (unless already changed)
            if ($orderItem->getRelatedOrder() === $this) {
                $orderItem->setRelatedOrder(null);
            }
        }
        $this->updateTotalAmount(); // Aggiorna il totale quando un item è rimosso
        return $this;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateTotalAmount(): void
    {
        $total = 0.0;
        foreach ($this->getOrderItems() as $item) {
            if ($item->getPriceAtPurchase() !== null && $item->getQuantity() !== null) {
                $total += (float) $item->getPriceAtPurchase() * $item->getQuantity();
            }
        }
        $this->totalAmount = sprintf('%.2f', $total); // Formatta a due decimali
    }
}
