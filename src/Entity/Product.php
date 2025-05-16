<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post; // Se vuoi poter creare prodotti via API
use ApiPlatform\Metadata\Put;   // Se vuoi poter aggiornare prodotti via API
use App\Repository\ProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert; // Per la validazione
use Symfony\Component\Serializer\Annotation\Groups; // Per gruppi di serializzazione

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ApiResource(
    operations: [
        new Get(normalizationContext: ['groups' => ['product:read']]),
        new GetCollection(normalizationContext: ['groups' => ['product:read']]),
        // new Post(denormalizationContext: ['groups' => ['product:write']]), // Deselezionare se vuoi l'endpoint POST
        // new Put(denormalizationContext: ['groups' => ['product:write']]),   // Deselezionare se vuoi l'endpoint PUT
    ],
    normalizationContext: ['groups' => ['product:read']], // Gruppo di default per la lettura
    denormalizationContext: ['groups' => ['product:write']] // Gruppo di default per la scrittura
)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['product:read', 'order:read'])] // Visibile quando leggo un prodotto o un ordine
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 255)]
    #[Groups(['product:read', 'product:write', 'order:read', 'order:write'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['product:read', 'product:write', 'order:read', 'order:write'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    #[Groups(['product:read', 'product:write', 'order:read', 'order:write'])]
    private ?string $price = null; // Doctrine usa string per i decimali, Symfony li gestisce

    // Potresti aggiungere una relazione inversa a OrderItem se necessario,
    // ma per ora la manteniamo semplice.

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
        $this.description = $description;
        return $this;
    }

    public function getPrice(): ?string
    {
        return $this.price;
    }

    public function setPrice(string $price): static
    {
        $this.price = $price;
        return $this;
    }
}
