<?php
// src/Controller/OrderController.php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request; // Importa Request
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;

#[Route('/api/orders')]
class OrderController extends AbstractController
{
    private EntityManagerInterface $em;
    private SerializerInterface $serializer;
    private ValidatorInterface $validator;
    private OrderRepository $orderRepository;
    private ProductRepository $productRepository;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $em,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        OrderRepository $orderRepository,
        ProductRepository $productRepository,
        LoggerInterface $logger
    ) {
        $this->em = $em;
        $this->serializer = $serializer;
        $this->validator = $validator;
        $this->orderRepository = $orderRepository;
        $this->productRepository = $productRepository;
        $this->logger = $logger;
    }

    private function normalizeOrder(Order $order): array
    {
        $orderItemsData = [];
        foreach ($order->getOrderItems() as $item) {
            $productData = null;
            if ($item->getProduct()) {
                $productData = [
                    'id' => $item->getProduct()->getId(),
                    'name' => $item->getProduct()->getName(),
                    'description' => $item->getProduct()->getDescription(),
                    'price' => $item->getProduct()->getPrice(),
                ];
            }
            $orderItemsData[] = [
                'id' => $item->getId(),
                'quantity' => $item->getQuantity(),
                'priceAtPurchase' => $item->getPriceAtPurchase(),
                'subtotal' => $item->getSubtotal(),
                'product' => $productData,
            ];
        }

        return [
            'id' => $order->getId(),
            'orderNumber' => $order->getOrderNumber(),
            'customerName' => $order->getCustomerName(),
            'orderDate' => $order->getOrderDate() ? $order->getOrderDate()->format(\DateTimeInterface::ATOM) : null,
            'description' => $order->getDescription(),
            'status' => $order->getStatus(),
            'totalAmount' => $order->getTotalAmount(),
            'orderItems' => $orderItemsData,
        ];
    }

    #[Route('', name: 'api_orders_list', methods: ['GET'])]
    public function index(Request $request): JsonResponse // Inietta Request
    {
        // Recupera i parametri dalla query string
        $filterOrderDate = $request->query->get('orderDate'); // Corrisponde a 'orderDate' in OrderService
        $searchTerm = $request->query->get('q'); // Corrisponde a 'q' in OrderService

        $this->logger->info('Richiesta lista ordini ricevuta.', [
            'filterOrderDate' => $filterOrderDate,
            'searchTerm' => $searchTerm
        ]);

        // Inizia con una query di base
        $queryBuilder = $this->orderRepository->createQueryBuilder('o');

        if ($filterOrderDate) {
            try {
                // Converte la stringa data in un oggetto DateTimeImmutable
                // Il frontend invia 'YYYY-MM-DD'
                $dateObject = new \DateTimeImmutable($filterOrderDate);
                // Filtra per l'intera giornata. Il campo orderDate è DATETIME_IMMUTABLE.
                // Quindi dobbiamo cercare ordini tra l'inizio e la fine del giorno specificato.
                $queryBuilder
                    ->andWhere('o.orderDate >= :startOfDay')
                    ->andWhere('o.orderDate <= :endOfDay')
                    ->setParameter('startOfDay', $dateObject->setTime(0, 0, 0))
                    ->setParameter('endOfDay', $dateObject->setTime(23, 59, 59));
                $this->logger->info('Applicato filtro per data.', ['date' => $filterOrderDate]);
            } catch (\Exception $e) {
                // Gestisci data non valida se necessario, o ignorala
                $this->logger->warning('Formato data non valido per il filtro.', ['date' => $filterOrderDate, 'error' => $e->getMessage()]);
            }
        }

        if ($searchTerm) {
            // Filtra per searchTerm su più campi (es. orderNumber, customerName)
            $queryBuilder
                ->andWhere($queryBuilder->expr()->orX(
                    $queryBuilder->expr()->like('LOWER(o.orderNumber)', ':searchTerm'),
                    $queryBuilder->expr()->like('LOWER(o.customerName)', ':searchTerm')
                    // Aggiungi altri campi se necessario, es. o.description
                ))
                ->setParameter('searchTerm', '%' . strtolower($searchTerm) . '%');
            $this->logger->info('Applicato filtro per searchTerm.', ['term' => $searchTerm]);
        }
        
        // Ordina per data decrescente (o altro criterio)
        $queryBuilder->orderBy('o.orderDate', 'DESC');

        $orders = $queryBuilder->getQuery()->getResult();

        $this->logger->info(sprintf('Trovati %d ordini da restituire dopo i filtri.', count($orders)));

        $data = [];
        foreach ($orders as $order) {
            $data[] = $this->normalizeOrder($order);
        }
        return $this->json($data);
    }

    // ... (resto dei metodi create, show, update, delete come prima) ...
    #[Route('/{id}', name: 'api_orders_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $order = $this->orderRepository->find($id);
        if (!$order) {
            return $this->json(['message' => 'Ordine non trovato.'], JsonResponse::HTTP_NOT_FOUND);
        }
        return $this->json($this->normalizeOrder($order));
    }

    #[Route('', name: 'api_orders_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->logger->info('Richiesta di creazione ordine ricevuta.', ['content' => $request->getContent()]);

        try {
            $data = json_decode($request->getContent(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('JSON non valido nella richiesta.', ['error' => json_last_error_msg()]);
                return $this->json(['message' => 'JSON non valido: ' . json_last_error_msg()], JsonResponse::HTTP_BAD_REQUEST);
            }

            $order = new Order();
            $order->setOrderNumber($data['orderNumber'] ?? uniqid('ORD-'));
            $order->setCustomerName($data['customerName'] ?? '');
            $order->setOrderDate(isset($data['orderDate']) ? new \DateTimeImmutable($data['orderDate']) : new \DateTimeImmutable());
            $order->setDescription($data['description'] ?? null);
            $order->setStatus($data['status'] ?? 'Pending');

            if (empty($data['orderItems'])) {
                 $this->logger->warning('Tentativo di creare ordine senza items.');
                return $this->json(['message' => "L'ordine deve contenere almeno un prodotto."], JsonResponse::HTTP_BAD_REQUEST);
            }

            foreach ($data['orderItems'] as $itemData) {
                $productPayload = $itemData['product'] ?? null;
                if (!$productPayload || empty($productPayload['name']) || !isset($productPayload['price'])) {
                    $this->logger->warning('Dati prodotto mancanti o incompleti per un item.', ['itemData' => $itemData]);
                    return $this->json(['message' => 'Nome e prezzo del prodotto sono obbligatori per ogni item.'], JsonResponse::HTTP_BAD_REQUEST);
                }

                $product = $this->productRepository->findOneBy(['name' => $productPayload['name']]);
                if (!$product) {
                    $product = new Product();
                    $product->setName($productPayload['name']);
                    $product->setPrice((string)$productPayload['price']);
                    $product->setDescription($productPayload['description'] ?? null);

                    $productViolations = $this->validator->validate($product);
                    if (count($productViolations) > 0) {
                        $this->logger->warning('Violazioni di validazione per nuovo prodotto.', ['violations' => (string)$productViolations]);
                        return $this->json(['message' => 'Errore validazione prodotto', 'violations' => (string)$productViolations], JsonResponse::HTTP_BAD_REQUEST);
                    }
                    $this->em->persist($product);
                } else {
                    // Opzionale: aggiorna il prezzo del prodotto esistente se è cambiato?
                }


                $orderItem = new OrderItem();
                $orderItem->setProduct($product);
                $orderItem->setQuantity((int)($itemData['quantity'] ?? 1));
                $orderItem->setPriceAtPurchase((string)($itemData['priceAtPurchase'] ?? $productPayload['price']));

                $order->addOrderItem($orderItem);
            }

            $violations = $this->validator->validate($order);
            if (count($violations) > 0) {
                $this->logger->warning('Violazioni di validazione per ordine.', ['violations' => (string)$violations]);
                return $this->json(['message' => 'Errore di validazione', 'violations' => (string)$violations], JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $this->em->persist($order);
            $this->em->flush();
            
            $this->logger->info('Ordine creato con successo.', ['orderId' => $order->getId()]);
            return $this->json($this->normalizeOrder($order), JsonResponse::HTTP_CREATED);

        } catch (\Exception $e) {
            $this->logger->error('Errore durante la creazione dell\'ordine.', ['exception' => $e]);
            return $this->json([
                'message' => 'Si è verificato un errore interno.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'api_orders_update', methods: ['PUT'])]
    public function update(Request $request, int $id): JsonResponse
    {
        $this->logger->info('Richiesta di aggiornamento ordine ricevuta.', ['orderId' => $id, 'content' => $request->getContent()]);
        $order = $this->orderRepository->find($id);
        if (!$order) {
            $this->logger->warning('Tentativo di aggiornare ordine non esistente.', ['orderId' => $id]);
            return $this->json(['message' => 'Ordine non trovato.'], JsonResponse::HTTP_NOT_FOUND);
        }

        try {
            $data = json_decode($request->getContent(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                 $this->logger->error('JSON non valido in richiesta di aggiornamento.', ['error' => json_last_error_msg()]);
                return $this->json(['message' => 'JSON non valido: ' . json_last_error_msg()], JsonResponse::HTTP_BAD_REQUEST);
            }

            if (isset($data['orderNumber'])) $order->setOrderNumber($data['orderNumber']);
            if (isset($data['customerName'])) $order->setCustomerName($data['customerName']);
            if (isset($data['orderDate'])) $order->setOrderDate(new \DateTimeImmutable($data['orderDate']));
            if (isset($data['description'])) $order->setDescription($data['description']);
            if (isset($data['status'])) $order->setStatus($data['status']);

            if (isset($data['orderItems'])) {
                foreach ($order->getOrderItems() as $existingItem) {
                    $order->removeOrderItem($existingItem); 
                    $this->em->remove($existingItem);
                }

                foreach ($data['orderItems'] as $itemData) {
                    $productPayload = $itemData['product'] ?? null;
                    if (!$productPayload || empty($productPayload['name']) || !isset($productPayload['price'])) {
                        $this->logger->warning('Dati prodotto mancanti in aggiornamento.', ['itemData' => $itemData]);
                        return $this->json(['message' => 'Nome e prezzo del prodotto sono obbligatori per ogni item.'], JsonResponse::HTTP_BAD_REQUEST);
                    }

                    $product = $this->productRepository->findOneBy(['name' => $productPayload['name']]);
                    if (!$product) {
                        $product = new Product();
                        $product->setName($productPayload['name']);
                        $product->setPrice((string)$productPayload['price']);
                        $product->setDescription($productPayload['description'] ?? null);
                        $this->em->persist($product);
                    }
                    
                    $orderItem = new OrderItem();
                    $orderItem->setProduct($product);
                    $orderItem->setQuantity((int)($itemData['quantity'] ?? 1));
                    $orderItem->setPriceAtPurchase((string)($itemData['priceAtPurchase'] ?? $productPayload['price']));
                    $order->addOrderItem($orderItem);
                }
            }

            $violations = $this->validator->validate($order);
            if (count($violations) > 0) {
                $this->logger->warning('Violazioni validazione aggiornamento ordine.', ['violations' => (string)$violations]);
                return $this->json(['message' => 'Errore di validazione', 'violations' => (string)$violations], JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $this->em->flush();
            $this->logger->info('Ordine aggiornato con successo.', ['orderId' => $order->getId()]);
            return $this->json($this->normalizeOrder($order));

        } catch (\Exception $e) {
            $this->logger->error('Errore durante l\'aggiornamento dell\'ordine.', ['orderId' => $id, 'exception' => $e]);
            return $this->json([
                'message' => 'Si è verificato un errore interno durante l\'aggiornamento.',
                 'error' => $e->getMessage()
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'api_orders_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->logger->info('Richiesta di eliminazione ordine ricevuta.', ['orderId' => $id]);
        $order = $this->orderRepository->find($id);
        if (!$order) {
            $this->logger->warning('Tentativo di eliminare ordine non esistente.', ['orderId' => $id]);
            return $this->json(['message' => 'Ordine non trovato.'], JsonResponse::HTTP_NOT_FOUND);
        }

        try {
            $this->em->remove($order);
            $this->em->flush();
            $this->logger->info('Ordine eliminato con successo.', ['orderId' => $id]);
            return $this->json(null, JsonResponse::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            $this->logger->error('Errore durante l\'eliminazione dell\'ordine.', ['orderId' => $id, 'exception' => $e]);
            return $this->json([
                'message' => 'Si è verificato un errore interno durante l\'eliminazione.',
                'error' => $e->getMessage()
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
