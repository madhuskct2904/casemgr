<?php

namespace App\Controller;

use App\Domain\TutorialCategory\TutorialCategoryService;
use App\Entity\Tutorial;
use App\Entity\TutorialCategory;
use App\Entity\Users;
use App\Exception\AuthException;
use App\Exception\EntityException;
use App\Exception\ExceptionMessage;
use App\Service\S3ClientFactory;
use Aws\S3\Exception\S3Exception;
use Dompdf\Exception;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use function Sentry\captureException;

class TutorialsController extends Controller
{
    private TutorialCategoryService $tutorialCategoryService;

    public function __construct(TutorialCategoryService $tutorialCategoryService)
    {
        $this->tutorialCategoryService = $tutorialCategoryService;
    }

    public function categoriesIndexAction(): JsonResponse
    {
        $this->verifyAccess();

        $em = $this->getDoctrine()->getManager();

        $categories = $em->getRepository('App:TutorialCategory')->findAll();

        $categoriesArr = [];

        foreach ($categories as $category) {
            $categoriesArr[$category->getSort()]['title'] = $category->getTitle();
            $categoriesArr[$category->getSort()]['id'] = $category->getId();
        }

        $categoriesArr = array_values($categoriesArr);

        return $this->getResponse()->success(['categories' => $categoriesArr]);
    }

    public function addCategoryAction(): JsonResponse
    {
        $this->verifyAccess(Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR']);

        $em = $this->getDoctrine()->getManager();

        try {
            $maxSortOrder = $em->getRepository('App:TutorialCategory')->getMaxSort();
            $title = $this->getRequest()->param('title');

            $category = new TutorialCategory();
            $category->setSort($maxSortOrder + 1);
            $category->setTitle($title);
            $em->persist($category);
            $em->flush();
        } catch (Exception $e) {
            captureException($e); // capture exception by Sentry

            return $this->getResponse()->error(ExceptionMessage::DEFAULT);
        }

        return $this->getResponse()->success([
            'category' => [
                'id'    => $category->getId(),
                'title' => $category->getTitle(),
            ]
        ]);
    }

    /**
     * @throws AuthException|EntityException
     */
    public function updateCategoryAction(): JsonResponse
    {
        $this->verifyAccess(Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR']);
        $this->tutorialCategoryService->updateCategoryTitle(
            $this->tutorialCategoryService->getCategory($this->getRequest()->param('id')),
            $this->getRequest()->param('title')
        );

        return $this->getResponse()->noContent();
    }

    public function getCategoryAction($categoryId): JsonResponse
    {
        $this->verifyAccess();

        $em = $this->getDoctrine()->getManager();

        $category = $em->getRepository('App:TutorialCategory')->find($categoryId);

        if (!$category) {
            return $this->getResponse()->error(ExceptionMessage::NOT_FOUND_CATEGORY, 404);
        }

        $categoryArr = [
            'id'    => $category->getId(),
            'title' => $category->getTitle()
        ];

        $tutorials = $category->getTutorials();
        $tutorialsArr = [];

        foreach ($tutorials as $tutorial) {
            $tutorialsArr[] = [
                'header'    => $tutorial->getHeader(),
                'subheader' => $tutorial->getSubheader(),
                'file'      => $tutorial->getFile(),
                'filename'  => $tutorial->getFilename(),
                'thumbFile' => $tutorial->getThumbFile(),
                'id'        => $tutorial->getId()
            ];
        }

        usort($tutorialsArr, function ($item1, $item2) {
            return $item1['header'] <=> $item2['header'];
        });

        return $this->getResponse()->success([
            'category'  => $categoryArr,
            'tutorials' => $tutorialsArr
        ]);
    }

    public function getTutorialAction($tutorialId): JsonResponse
    {
        $this->verifyAccess();

        $em = $this->getDoctrine()->getManager();

        $tutorial = $em->getRepository('App:Tutorial')->find($tutorialId);

        if (!$tutorial) {
            return $this->getResponse()->error(ExceptionMessage::NOT_FOUND_TUTORIAL, 404);
        }

        $tutorialArr = [
            'id'        => $tutorial->getId(),
            'header'    => $tutorial->getHeader(),
            'subheader' => $tutorial->getSubheader(),
            'thumbFile' => $tutorial->getThumbFile(),
            'file'      => $tutorial->getFile(),
            'filename'  => $tutorial->getFilename(),
        ];

        return $this->getResponse()->success([
            'tutorial'   => $tutorialArr,
            'categoryId' => $tutorial->getCategory()->getId()
        ]);
    }

    public function updateTutorialAction(): JsonResponse
    {
        $this->verifyAccess(Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR']);

        $em = $this->getDoctrine()->getManager();
        $tutorialId = $this->getRequest()->param('tutorialId');
        $tutorial = $em->getRepository('App:Tutorial')->find($tutorialId);

        if (!$tutorial) {
            return $this->getResponse()->error(ExceptionMessage::WRONG_TUTORIAL, 422);
        }

        $tutorialData = $this->getRequest()->param('tutorialData');
        $categoryId = $this->getRequest()->param('categoryId');

        if ($categoryId) {
            $category = $em->getRepository('App:TutorialCategory')->find($categoryId);

            if ($category) {
                $tutorial->setCategory($category);
            }
        }

        $tutorial->setHeader($tutorialData['header']);
        $tutorial->setSubheader($tutorialData['subheader']);
        $tutorial->setFilename($tutorialData['filename']);

        $em->flush();

        return $this->getResponse()->success(['message' => 'Tutorial updated!']);
    }

    public function uploadFileAction(S3ClientFactory $s3ClientFactory): JsonResponse
    {
        $this->verifyAccess(Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR']);

        $files = $this->getRequest()->files();
        $currentCategoryId = $this->getRequest()->post('categoryId');

        if (!$currentCategoryId) {
            return $this->getResponse()->error(ExceptionMessage::WRONG_CATEGORY, 422);
        }

        $em = $this->getDoctrine()->getManager();
        $currentCategory = $em->getRepository('App:TutorialCategory')->find($currentCategoryId);

        foreach ($files as $file) {
            $uploaded = new UploadedFile($file['tmp_name'], $file['name'], $file['type'], $file['size']);
            $fileName = sprintf('%s.%s', md5(time() . $file['name'] . mt_rand()), $uploaded->guessExtension());

            $tutorial = new Tutorial();
            $tutorial->setCategory($currentCategory);
            $tutorial->setHeader('');
            $tutorial->setSubheader('');
            $tutorial->setFileSize($file['size']);
            $tutorial->setFilename($file['name']);
            $tutorial->setFile($fileName);
            $tutorial->setCreatedAt(new \DateTime());

            $em->persist($tutorial);
            $client = $s3ClientFactory->getClient();
            $bucket = $this->getParameter('aws_bucket_name');

            try {
                $client->putObject([
                    'Bucket'     => $bucket,
                    'Key'        => 'tutorials/' . $fileName,
                    'SourceFile' => $file['tmp_name'],
                    //'ACL'           => 'public-read'
                ]);
            } catch (S3Exception $e) {
                captureException($e); // capture exception by Sentry

                return $this->getResponse()->error(ExceptionMessage::DEFAULT);
            }
        }

        $em->flush();

        return $this->getResponse()->success([]);
    }

    public function uploadThumbFileAction(S3ClientFactory $s3ClientFactory): JsonResponse
    {
        $this->verifyAccess(Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR']);

        $files = $this->getRequest()->files();
        $tutorialId = $this->getRequest()->post('tutorialId');

        if (!$tutorialId) {
            return $this->getResponse()->error(ExceptionMessage::WRONG_TUTORIAL, 422);
        }

        $em = $this->getDoctrine()->getManager();
        $tutorial = $em->getRepository('App:Tutorial')->find($tutorialId);

        foreach ($files as $file) {
            $uploaded = new UploadedFile($file['tmp_name'], $file['name'], $file['type'], $file['size']);
            $fileName = sprintf('%s.%s', md5(time() . $file['name'] . mt_rand()), $uploaded->guessExtension());

            $tutorial->setThumbFile($fileName);

            $client = $s3ClientFactory->getClient();
            $bucket = $this->getParameter('aws_bucket_name');

            try {
                $client->putObject([
                    'Bucket'     => $bucket,
                    'Key'        => 'tutorials/' . $fileName,
                    'SourceFile' => $file['tmp_name'],
                    'ACL'        => 'public-read'
                ]);
            } catch (S3Exception $e) {
                captureException($e); // capture exception by Sentry

                return $this->getResponse()->error(ExceptionMessage::DEFAULT);
            }
        }
        $em->flush();

        return $this->getResponse()->success(['message' => 'Thumb updated!', 'filename' => $fileName]);
    }

    public function downloadFileAction(S3ClientFactory $s3ClientFactory, Request $request, int $tutorialId): ?Response
    {
        $this->verifyAccess(Users::ACCESS_LEVELS['VOLUNTEER']);

        $em = $this->getDoctrine()->getManager();

        $tutorial = $em->getRepository('App:Tutorial')->find($tutorialId);

        if (!$tutorial) {
            throw new NotFoundHttpException(404);
        }

        $fileName = $tutorial->getFile();
        $downloadName = $tutorial->getFilename();

        $client = $s3ClientFactory->getClient();
        $bucket = $this->getParameter('aws_bucket_name');

        try {
            $result = $client->getObject([
                'Bucket' => $bucket,
                'Key'    => 'tutorials/' . $fileName
            ]);

            return new Response(
                $result['Body'],
                200,
                [
                    'Content-Type'        => $result['ContentType'],
                    'Content-Disposition' => sprintf('attachment; filename="%s"', $downloadName === '' ? $fileName : $downloadName),
                ]
            );
        } catch (S3Exception $e) {
            throw new NotFoundHttpException(404);
        }
    }

    public function removeAction(): JsonResponse
    {
        $this->verifyAccess(Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR']);

        $em = $this->getDoctrine()->getManager();

        $tutorialId = $this->getRequest()->param('id');
        $tutorial = $em->getRepository('App:Tutorial')->find($tutorialId);

        if (!$tutorial) {
            return $this->getResponse()->error(ExceptionMessage::WRONG_TUTORIAL, 422);
        }

        $em->remove($tutorial);
        $em->flush();

        return $this->getResponse()->success(['message' => 'Tutorial removed']);
    }


    public function removeCategoryAction(): JsonResponse
    {
        $this->verifyAccess(Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR']);

        $em = $this->getDoctrine()->getManager();

        $categoryId = $this->getRequest()->param('id');
        $category = $em->getRepository('App:TutorialCategory')->find($categoryId);

        if (!$category) {
            return $this->getResponse()->error(ExceptionMessage::WRONG_TUTORIAL, 422);
        }

        $em->remove($category);
        $em->flush();

        return $this->getResponse()->success(['message' => 'Category removed']);
    }

    public function indexAction(): JsonResponse
    {
        $this->verifyAccess(Users::ACCESS_LEVELS['VOLUNTEER']);

        $em = $this->getDoctrine()->getManager();
        $tutorials = $em->getRepository('App:Tutorial')->findAll();
        $tutorialsArr = [];

        foreach ($tutorials as $tutorial) {
            $tutorialsArr[] = [
                'header'     => $tutorial->getHeader(),
                'subheader'  => $tutorial->getSubheader(),
                'file'       => $tutorial->getFile(),
                'filename'   => $tutorial->getFilename(),
                'thumbFile'  => $tutorial->getThumbFile(),
                'id'         => $tutorial->getId(),
                'categoryId' => $tutorial->getCategory()->getId()
            ];
        }

        usort($tutorialsArr, function ($item1, $item2) {
            return $item1['header'] <=> $item2['header'];
        });

        return $this->getResponse()->success([
            'tutorials' => $tutorialsArr
        ]);

    }

}
