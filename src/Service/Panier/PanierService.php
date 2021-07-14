<?php
namespace App\Service\Panier;

use App\Repository\ArticleRepository;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class PanierService
{
    public $session;
    public $articleRepository;

    public function __construct(SessionInterface $session, ArticleRepository $articleRepository)
    {
        $this->session=$session;
        $this->articleRepository=$articleRepository;
    }

    /**
     * @Route("/add{id}", name="add")
     * @param int $id
     */
    public function add(int $id)
    {
        $panier=$this->session->get('panier',[]);

        if (!empty($panier[$id])): // si il existe une entrée dans panier à l'indice $id, l'article est donc deja present, on incremente donc la quantité
            $panier[$id]++;
        else: // sinon on l'initialise a 1 en quantité
            $panier[$id]=1;
        endif;

        $this->session->set('panier', $panier); //on charge a present les donnees dans notre session
    }

    public function remove(int $id)
    {
        $panier=$this->session->get('panier',[]);

        if (!empty($panier[$id]) && $panier[$id]>1):
            $panier[$id]--; // si on a un minim de 2 article en panier, on decremente la quantité
        else:
            unset($panier[$id]);
        endif;

        $this->session->set('panier',$panier);
    }

    public function delete(int $id)
    {
        $panier=$this->session->get('panier',[]);

        if (!empty($panier[$id])):
            unset($panier[$id]);
        endif;

        $this->session->set('panier', $panier);
    }

    public function deleteAll()
    {

        $this->session->set('panier',[]);
    }

    public function getFullPanier()
    {
        //$panier[] = $id=>quantite;

        $panier=$this->session->get('panier',[]);

        $panierDetail=[];

        foreach ($panier as $id => $quantite):
            $panierDetail[]=[
                'article' => $this->articleRepository->find($id),
                'quantite' => $quantite
            ];

        endforeach;

        return $panierDetail;
    }

    public function getTotal()
    {
        $total=0;

        foreach ($this->getFullPanier() as $item): // this->getFullPanier() nous retourne notre tableau multidimentionnel $panierDetail

            $total+= $item['article']->getPrix() * $item['quantite'];

        endforeach;

        return $total;
    }
}