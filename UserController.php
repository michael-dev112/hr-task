<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\UserRepository;
use App\Repository\CommentRepository;
USE App\Service\DataBase;
use Psr\Cache\CacheItemPoolInterface;

class UserController extends AbstractController
{

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly CacheItemPoolInterface $c,
        private readonly CommentRepository $commentRepository
    )
    {
    }

    /**
     * @Route("/api/users/add", methods={"POST"}, name="add_to_cart")
     */
    public function add(Request $request): JsonResponse
    {
        $DB = DataBase::getInstance();
        $data = json_decode($request->getContent(), true);
        $user = $DB->query("SELECT * FROM users WHERE email = {$data['email']}")->fetch();
        $item = $DB->query("SELECT * FROM items WHERE id = {$data['id']}")->fetch();

        if ($user['balance'] < $item['cost']) {
            return new JsonResponse('Not enough money', 400);
        }

        $DB->query('UPDATE users SET balance = balance - ' . $item['cost']);
        $DB->query("INSERT INTO items_users (user_id, item_id) VALUES (  {$user['id']}, {$item['id']})");

        return new JsonResponse('Added');
    }

    /**
     * @Route("/api/users/{id}", methods={"GET"}, name="api_user_show")
     */
    public function show(Request $request, $id): JsonResponse
    {
        $cacheKey = 'user_' . $id;
        $cachedUser = $this->c->getItem($cacheKey);

        if (!$cachedUser->isHit()) {
            $user = $this->userRepository->find($id);
            if (!$user) {
                return new JsonResponse('User not found', 404);
            }

            $cachedUser->set($user);
            $this->c->save($cachedUser);
        } else {
            $user = $cachedUser->get();
        }

        return $this->json($user);
    }

    /**
     * @Route("/api/users/{id}", methods={"PUT"}, name="api_user_update")
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return new JsonResponse('User not found', 404);
        }

        $cacheKey = 'user_' . $id;
        $this->c->deleteItem($cacheKey);

        $this->getDoctrine()->getManager()->flush();

        return new JsonResponse('User updated successfully');
    }

    /**
     * @Route("/api/users/{id}/comments", methods={"GET"}, name="api_user_comments")
     */
    public function getUserComments(Request $request, $id): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return new JsonResponse(['message' => 'User not found'], 404);
        }

        $comments = $this->commentRepository->findBy(['user' => $user]);

        return $this->json($comments);
    }
}
