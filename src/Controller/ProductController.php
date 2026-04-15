<?php
namespace App\Controller;

use Stripe\StripeClient;
use App\Repository\ProductRepository;
use App\Entity\Product;
use App\Entity\Cart;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Repository\CartRepository;
class ProductController extends AbstractController {

    #[Route('/')]
    public function catalog(ProductRepository $productRepository) : Response 
    {
        $todos = $productRepository->findAll();
        return $this->render('product/catalog.html.twig', ['products' => $todos]);
    }

    #[Route('/product/create')]
    #[IsGranted('ROLE_ADMIN')]
    public function create() : Response 
    {
        return $this->render('product/create.html.twig');
    }

    #[Route('/product/save', methods: ['POST'])]
    public function save(Request $request, EntityManagerInterface $entityManager): Response
    {
    $name = $request->request->get('name');
    $description = $request->request->get('description');
    $price = $request->request->get('price');
    $estock = $request->request->get('estock');
    $imageFile = $request->files->get('image');
    $imageUrl = null;

    if ($imageFile) {
        Configuration::instance($_ENV['CLOUDINARY_URL']);
        $upload = new UploadApi();

        $response = $upload->upload($imageFile->getRealPath(), [
            'folder' => 'loja_uoou/produtos', 
            'verify' => false
        ]);

        $imageUrl = $response['secure_url'];
    }

    $entityManager->beginTransaction();

    try {
        $product = new Product();
        $product->setName($name);
        $product->setDescription($description);
        $product->setPrice((float) $price);
        $product->setestock((int) $estock);
        $product->setImagePath($imageUrl);

        $entityManager->persist($product);

        $stripe = new StripeClient($_ENV['STRIPE_SECRET_KEY']);

        

        $stripeProduct = $stripe->products->create([
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'images' => [$imageUrl],
        ]);

        $stripe->prices->create([
            'unit_amount' => $product->getPrice() * 100,
            'currency' => 'brl',
            'product' => $stripeProduct->id,
        ]);

        $product->setStripeProductId($stripeProduct->id);

        $entityManager->flush(); 
        $entityManager->commit();

        $this->addFlash('success', 'Produto criado e sincronizado com Stripe!');

    } catch (\Exception $e) {
        $entityManager->rollback();
        
        $this->addFlash('error', 'Erro ao salvar: ' . $e->getMessage());
        
    }

    return $this->redirectToRoute('app_product_catalog');
    }

    #[Route('/cart/add/{id}', name: 'cart_add')]
    public function add(int $id, Request $request, CartRepository $cartRepo, EntityManagerInterface $em, ProductRepository $productRepository): Response
    {
        $product = $productRepository->find($id);
    
        if (!$product) {
            $this->addFlash('error', 'Produto não encontrado.');
            return $this->redirectToRoute('app_product_catalog');
        }

        $user = $this->getUser();

        if ($user) {
            $cartItem = $cartRepo->findOneBy(['user' => $user, 'product' => $product]);

            if ($cartItem) {
                $cartItem->setQuantity($cartItem->getQuantity() + 1);
            } else {
                $cartItem = new Cart();
                $cartItem->setUser($user);
                $cartItem->setProduct($product);
                $cartItem->setQuantity(1);
            }

            $em->persist($cartItem);
            $em->flush();

        } else {
            $session = $request->getSession();
            $cart = $session->get('cart', []);

            if (!empty($cart[$id])) {
                $cart[$id]++;
            } else {
                $cart[$id] = 1;
            }

            $session->set('cart', $cart);
        }

       


        $this->addFlash('success', 'Produto adicionado ao carrinho!');
        return $this->redirectToRoute('app_product_catalog');
    }
}
