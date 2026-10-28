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
use App\Repository\PostRepository;
use App\Entity\Comment;
use App\Form\CommentFormType;
use Symfony\Component\HttpFoundation\JsonResponse;



final class PageController extends AbstractController
{
   private const POSTS_PER_PAGE = 5; //Ponlo a 1 para pruebas

#[Route('/blog/{page}', name: 'blog')]
public function index(ManagerRegistry $doctrine, int $page = 1): Response
{
    $page = max(1, $page);

    $repository = $doctrine->getRepository(Post::class);
    //Aquí obtenemos los datos con el método creado en el repositorio
    $posts = $repository->findPaginados($page, self::POSTS_PER_PAGE);
    $recents = $repository->findRecents();

    $total = count($posts);
    $totalPages = max(1, (int) ceil($total / self::POSTS_PER_PAGE));

    // Si piden una página que no existe, redirigimos a la última
    if ($page > $totalPages) {
        return $this->redirectToRoute('blog', ['page' => $totalPages]);
    }

    return $this->render('blog/blog.html.twig', [
        'posts' => $posts,
        'recents' => $recents,
        'page'       => $page,
        'totalPages' => $totalPages,
        'total'        => $total,
    ]);
}

    #[Route('/blog', name: 'blog')]
public function blog(CategoryRepository $categoryRepository, ManagerRegistry $doctrine): Response
{
    $postRepository = $doctrine->getRepository(Post::class);

    return $this->render('blog.html.twig', [
        'categories' => $categoryRepository->findAllOrderedByName(),
        'posts' => $postRepository->findAll(),
        'recents' => $postRepository->findRecents(),
    ]);
}

   #[Route('/single_post/{slug}', name: 'single_post')]
public function post(ManagerRegistry $doctrine, Request $request, $slug): Response
{
    $repository = $doctrine->getRepository(Post::class);
    $post = $repository->findOneBy(['slug' => $slug]);

    if (!$post) {
        throw $this->createNotFoundException('Post no encontrado.');
    }

    $recents = $repository->findRecents();
    $comments = $doctrine->getRepository(Comment::class)
        ->findBy(['post' => $post], ['id' => 'DESC']);

    $comment = new Comment();
    $form = $this->createForm(CommentFormType::class, $comment);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $comment->setPost($post);
        $post->setNumComments($post->getNumComments() + 1);

        $entityManager = $doctrine->getManager();
        $entityManager->persist($comment);
        $entityManager->flush();

        return $this->redirectToRoute('single_post', ['slug' => $post->getSlug()]);
    }

    return $this->render('single_post.html.twig', [
        'post' => $post,
        'recents' => $recents,
        'comments' => $comments,
        'commentForm' => $form->createView(),
    ]);
}

#[Route('/single_post/{slug}/like', name: 'post_like')]
public function like(ManagerRegistry $doctrine, $slug): JsonResponse
{
    $repository = $doctrine->getRepository(Post::class);
    $post = $repository->findOneBy(["slug"=>$slug]);
    if ($post){
        $post->like();
        $entityManager = $doctrine->getManager();    
        $entityManager->persist($post);
        $entityManager->flush();
    }
    $data = ['success' => true, 'likes' => $post->getNumLikes()];
    return $this->json($data);

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


#[Route('/blog/buscar/{page}', name: 'blog_buscar')]
public function buscar(ManagerRegistry $doctrine, Request $request, int $page = 1): Response
{
    $page = max(1, $page);

    $repository = $doctrine->getRepository(Post::class);
    $searchTerm = $request->query->get('searchTerm', '');
    $posts = $repository->findBuscadosPaginados($searchTerm, $page, self::POSTS_PER_PAGE);
    $recents = $repository->findRecents();

    $total = count($posts);
    $totalPages = max(1, (int) ceil($total / self::POSTS_PER_PAGE));

    // Si piden una página que no existe, redirigimos a la última (conservando la búsqueda)
    if ($page > $totalPages) {
        return $this->redirectToRoute('blog_buscar', ['page' => $totalPages, 'searchTerm' => $searchTerm]);
    }

    return $this->render('blog/blog.html.twig', [
        'posts'      => $posts,
        'recents'    => $recents,
        'searchTerm' => $searchTerm,
        'page'       => $page,
        'totalPages' => $totalPages,
        'total'      => $total,
    ]);
}





}
