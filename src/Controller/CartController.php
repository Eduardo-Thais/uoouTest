<?php
namespace App\Controller;

use App\Entity\Cart;
use App\Entity\Product;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\CartRepository;
use Doctrine\ORM\EntityManagerInterface;
class CartController extends AbstractController
{

    #[Route('/cart', name: 'cart_index')]
    public function index(Request $request, ProductRepository $repository,CartRepository $cartRepo): Response
    {
        $cartWithData = [];
        $user = $this->getUser();

        if ($user) {
            $cartItems = $cartRepo->findBy(['user' => $user]);
            foreach ($cartItems as $cartItem) {
                $cartWithData[] = [
                    'product' => $cartItem->getProduct(),
                    'quantity' => $cartItem->getQuantity()
                ];
            }
        } else {
            $session = $request->getSession();
            $cart = $session->get('cart', []);

            foreach ($cart as $id => $quantity) {
                $product = $repository->find($id);
                if ($product) { 
                    $cartWithData[] = [
                        'product' => $product,
                        'quantity' => $quantity
                    ];
                }
            }
        }

        return $this->render('cart/cart.html.twig', [
            'items' => $cartWithData
        ]);
    }

    #[Route('/cart/checkout', name: 'cart_stripe_checkout')]
    public function stripeCheckout(Request $request, ProductRepository $repository,CartRepository $cartRepo): Response
    {
        $stripe = new \Stripe\StripeClient($_ENV['STRIPE_SECRET_KEY']);

        if ($this->getUser()) {
            $cartItems = $cartRepo->findBy(['user' => $this->getUser()]);
            foreach ($cartItems as $item) {
                $product = $item->getProduct();
                $lineItems[] = $this->createStripeLineItem($stripe, $product, $item->getQuantity());
            }
        } else {
            $session = $request->getSession();
            $cart = $session->get('cart', []);
            foreach ($cart as $id => $quantity) {
                $product = $repository->find($id);
                
                // Buscamos o Price ID do Stripe para cada item
                $prices = $stripe->prices->all(['product' => $product->getStripeProductId(), 'limit' => 1]);
                
                $lineItems[] = [
                    'price' => $prices->data[0]->id,
                    'quantity' => $quantity,
                ];
            }
        }
        
       

        

        $checkoutSession = $stripe->checkout->sessions->create([
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => $this->generateUrl('app_product_catalog', [], 0) . '?success=true',
            'cancel_url' => $this->generateUrl('cart_index', [], 0),
        ]);

        return $this->redirect($checkoutSession->url, 303);
    }

    #[Route('/cart/remove/{id}', name: 'cart_remove')]
    public function remove(int $id, Request $request, ProductRepository $productRepository, CartRepository $cartRepo,EntityManagerInterface $em): Response
    {
        $product = $productRepository->find($id);
        $user = $this->getUser();

        if ($user) {
            $cartItem = $cartRepo->findOneBy(['user' => $user, 'product' => $product]);

            if ($cartItem) {
                $newQuantity = $cartItem->getQuantity() - 1;
                
                if ($newQuantity <= 0) {
                    $em->remove($cartItem); 
                } else {
                    $cartItem->setQuantity($newQuantity);
                    $em->persist($cartItem);
                }
                $em->flush();
            }

        } else {
            $session = $request->getSession();
            $cart = $session->get('cart', []);

            if (isset($cart[$id])) {
                if ($cart[$id] > 1) {
                    $cart[$id]--;
                } else {
                    unset($cart[$id]);
                }
                $session->set('cart', $cart);
            }
        }
        $this->addFlash('success', 'Produto removido do carrinho!');
        return $this->redirectToRoute('cart_index');
    }

    private function createStripeLineItem($stripe, $product, $quantity): array 
    {
        $stripeProductId = $product->getStripeProductId();
    
        $prices = $stripe->prices->all(['product' => $stripeProductId, 'limit' => 1]);
        
        if (empty($prices->data)) {
            throw new \Exception("Preço não encontrado no Stripe para o produto: " . $product->getName());
        }

        return [
            'price' => $prices->data[0]->id,
            'quantity' => $quantity,
        ];
    }
}