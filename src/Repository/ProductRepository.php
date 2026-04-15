<?php
namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        // Aqui o Symfony injeta a conexão automaticamente via ManagerRegistry
        parent::__construct($registry, Product::class);
    }

    public function buscarProdutosCaros(float $preco): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.price > :val')
            ->setParameter('val', $preco)
            ->getQuery()
            ->getResult();
    }
}