<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Contact;
use App\Form\ContactFormType;
use App\Entity\Category;
use App\Form\CategoryFormType;
use App\Repository\CategoryRepository;
use Symfony\Component\String\Slugger\SluggerInterface;
use App\Entity\Post;
use App\Form\PostFormType;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

final class PageController extends AbstractController
{
   #[Route('/', name: 'index')]
  public function index(ManagerRegistry $doctrine): Response
  {
      $categories = $doctrine->getRepository(Category::class)->findAll();
      return $this->render('index.html.twig', array(
          'categories' => $categories
      ));
  }

    #[Route('/about', name: 'about')]
    public function about(): Response
    {
        return $this->render('about.html.twig');
    }

    #[Route('/blog', name: 'blog')]
    public function blog(CategoryRepository $categoryRepository, ManagerRegistry $doctrine): Response
    {
        $posts = $doctrine->getRepository(Post::class)->findAll();

        return $this->render('blog.html.twig', [
            'categories' => $categoryRepository->findAllOrderedByName(),
            'posts' => $posts,
        ]);
    }

   #[Route('/single_post/{slug}', name: 'single_post')]
public function post(ManagerRegistry $doctrine, $slug): Response
{
    $repository = $doctrine->getRepository(Post::class);
    $post = $repository->findOneBy(["slug"=>$slug]);

    if (!$post) {
        throw $this->createNotFoundException('Post no encontrado.');
    }

    $recents = $repository->findRecents();
    return $this->render('single_post.html.twig', [
        'post' => $post,
        'recents' => $recents
    ]);
}



    #[Route('/contact', name: 'contact')]
    public function contacto(ManagerRegistry $doctrine, Request $request): Response
    {
    $contact = new Contact();
    $form = $this->createForm(ContactFormType::class, $contact);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
        $contacto = $form->getData();    
        $entityManager = $doctrine->getManager();    
        $entityManager->persist($contacto);
        $entityManager->flush();
        return $this->redirectToRoute('thankyou', []);
    }
    return $this->render('contact.html.twig', array(
        'form' => $form->createView()    
    ));
    }

    #[Route('/thankyou', name: 'thankyou')]
    public function thankyou(): Response
    {
        return $this->render('thankyou.html.twig');
    }

    #[Route('/blog/new', name: 'new_post')]
    public function newPost(ManagerRegistry $doctrine, Request $request, SluggerInterface $slugger): Response
    {
        // Comprobar si el usuario ha iniciado sesión; si no, redirigir al login
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $post = new Post();
        $form = $this->createForm(PostFormType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $post = $form->getData();

            // Quitamos los caracteres especiales del título para crear el slug
            $post->setSlug($slugger->slug($post->getTitle()));

            // Guardamos el usuario que crea el post y los contadores a 0
            $post->setPostUser($this->getUser());
            $post->setNumLikes(0);
            $post->setNumComments(0);

            // Gestionar la subida de la imagen
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error', 'No se pudo subir la imagen.');
                }

                $post->setImage($newFilename);
            }

            $entityManager = $doctrine->getManager();
            $entityManager->persist($post);
            $entityManager->flush();

            $this->addFlash('success', 'Post creado correctament.');
            return $this->redirectToRoute('single_post', ["slug" => $post->getSlug()]);
        }

        return $this->render('blog/new_post.html.twig', [
            'form' => $form->createView(),
        ]);

    }
    #[Route('/blog/{page}', name: 'blog_page', requirements: ['page' => '\d+'])]
    public function blogPage(ManagerRegistry $doctrine, int $page = 1): Response
    {
        $repository = $doctrine->getRepository(Post::class);
        $posts = $repository->findAll($page);

        return $this->render('blog/blog.html.twig', [
            'posts' => $posts,
        ]);
    }

}
