<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete; // Aggiunto se vuoi poter eliminare prodotti
use App\Repository\ProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ApiResource(
    operations: [
        new Get(normalizationContext: ['groups' => ['product:read', 'product:detail']]), // Gruppo specifico per il dettaglio
        new GetCollection(normalizationContext: ['groups' => ['product:list']]),      // Gruppo specifico per la lista
        new Post(
            denormalizationContext: ['groups' => ['product:write']],
            // security: "is_granted('ROLE_ADMIN')" // Esempio: solo gli admin possono creare prodotti
        ),
        new Put(
            denormalizationContext: ['groups' => ['product:write']],
            // security: "is_granted('ROLE_ADMIN')"  // Esempio: solo gli admin possono aggiornare
        ),
        // new Delete(security: "is_granted('ROLE_ADMIN')") // Esempio: solo gli admin possono eliminare
    ],
    // Normalization context di default (usato se non specificato nell'operazione)
    normalizationContext: ['groups' => ['product:read']],
    // Denormalization context di default
    denormalizationContext: ['groups' => ['product:write']],
    paginationItemsPerPage: 10
)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['product:read', 'product:list', 'product:detail', 'order:read', 'order:item:read'])] // Esposto in lettura
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Il nome del prodotto non può essere vuoto.")]
    #[Assert\Length(min: 2, minMessage: "Il nome del prodotto deve avere almeno {{ limit }} caratteri.")]
    #[Groups(['product:read', 'product:list', 'product:detail', 'product:write', 'order:read', 'order:item:read', 'order:write'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['product:read', 'product:detail', 'product:write', 'order:read', 'order:item:read'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: "Il prezzo del prodotto non può essere vuoto.")]
    #[Assert\PositiveOrZero(message: "Il prezzo del prodotto deve essere un numero positivo o zero.")]
    #[Groups(['product:read', 'product:list', 'product:detail', 'product:write', 'order:read', 'order:item:read', 'order:write'])]
    private ?string $price = null; // Doctrine usa stringhe per i decimali per precisione

    // Se avessi una relazione inversa OneToMany con OrderItem:
    // #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'product')]
    // private Collection $orderItems;
    // public function __construct() { $this->orderItems = new ArrayCollection(); }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
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

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;
        return $this;
    }

    // Se avessi la relazione inversa:
    // /**
    //  * @return Collection<int, OrderItem>
    //  */
    // public function getOrderItems(): Collection
    // {
    //     return $this->orderItems;
    // }
    // public function addOrderItem(OrderItem $orderItem): static
    // {
    //     if (!$this->orderItems->contains($orderItem)) {
    //         $this->orderItems->add($orderItem);
    //         $orderItem->setProduct($this);
    //     }
    //     return $this;
    // }
    // public function removeOrderItem(OrderItem $orderItem): static
    // {
    //     if ($this->orderItems->removeElement($orderItem)) {
    //         // set the owning side to null (unless already changed)
    //         if ($orderItem->getProduct() === $this) {
    //             $orderItem->setProduct(null);
    //         }
    //     }
    //     return $this;
    // }
}
