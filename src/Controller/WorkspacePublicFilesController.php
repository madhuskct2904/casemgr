<?php

namespace App\Controller;

use App\Entity\WorkspacePublicFile;
use App\Exception\ExceptionMessage;
use App\Service\S3ClientFactory;
use Aws\S3\Exception\S3Exception;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WorkspacePublicFilesController extends Controller
{
    public function indexAction()
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $user = $this->user();

        if (!$this->can(6, $user)) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 403);
        }

        $limit = $this->getRequest()->param('perPage', 10);
        $page = $this->getRequest()->param('page', 1);
        $offset = ($page * $limit) - $limit;

        $sort = $this->getRequest()->param('sort', null);

        $filters = $this->getRequest()->param('columnFilters', null);

        $getDeleted = $this->getRequest()->param('showDeleted', false);

        $account = $this->account();

        $workspaceFiles = $this->getDoctrine()->getRepository('App:WorkspacePublicFile')
            ->search($account, $offset, $limit, $filters, $sort, $getDeleted, false);

        $workspaceFileCount = $this->getDoctrine()->getRepository('App:WorkspacePublicFile')
            ->search($account, $offset, $limit, $filters, $sort, $getDeleted, true);


        $data = [
            'files'       => $workspaceFiles,
            'files_count' => $workspaceFileCount
        ];

        return $this->json([
            'data'   => $data,
            'status' => 'success'
        ]);
    }

    public function uploadAction(S3ClientFactory $s3ClientFactory)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $user = $this->user();

        if (!$this->can(6, $user)) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 403);
        }

        $files = $this->getRequest()->files();

        $files = $files['files'];

        $filesCount = count($files['name']);

        $response = [];

        $errorFiles = [];
        $em = $this->getDoctrine()->getManager();


        for ($i = 0; $i < $filesCount; $i++) {
            $upload = new UploadedFile(
                $files['tmp_name'][$i],
                $files['name'][$i],
                $files['type'][$i],
                $files['size'][$i]
            );

            $fileName = sprintf(
                '%s.%s',
                md5(time() . $files['name'] [$i] . mt_rand()),
                $upload->guessExtension()
            );

            $client = $s3ClientFactory->getClient();
            $bucket = $this->getParameter('aws_bucket_name');
            $prefix = $this->getParameter('aws_workspace_public_files_folder');

            try {
                $client->putObject([
                    'Bucket'     => $bucket,
                    'Key'        => $prefix . '/' . $fileName,
                    'SourceFile' => $files['tmp_name'][$i],
                ]);
            } catch (S3Exception $e) {
                $errorFiles[$i] = $upload;
                continue;
            }

            $publicUrl = bin2hex(openssl_random_pseudo_bytes(5)) . '/' . $files['name'][$i];

            $fileData = new WorkspacePublicFile();
            $fileData->setAccount($this->account());
            $fileData->setUser($this->user());
            $fileData->setUpdatedAt(new \DateTime());
            $fileData->setOriginalFilename($files['name'][$i]);
            $fileData->setServerFilename($fileName);
            $fileData->setDescription('');
            $fileData->setPublicUrl($publicUrl);
            $em->persist($fileData);
            $em->flush();

            $response['uploaded_files'][] = [
                'original_filename' => $files['name'][$i],
                'server_filename'   => $fileName,
                'public_url'        => $publicUrl
            ];
        }


        if (count($errorFiles)) {
            $response['errors'] = $errorFiles;
            $response['success'] = false;
        }

        return $this->getResponse()->success($response);
    }

    public function deleteAction(Request $request, WorkspacePublicFile $WorkspacePublicFile)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $account = $this->account();
        $user = $this->user();

        if ($account != $WorkspacePublicFile->getAccount()) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS);
        }

        if (!$this->can(6, $user)) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS);
        }

        $WorkspacePublicFile->setDeletedAt(new \DateTime());

        $em = $this->getDoctrine()->getManager();
        $em->persist($WorkspacePublicFile);
        $em->flush();

        return $this->getResponse()->success(['removed_file' => $WorkspacePublicFile->getId()]);
    }


    public function publishAction(Request $request, WorkspacePublicFile $WorkspacePublicFile)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $account = $this->account();
        $user = $this->user();

        if ($account != $WorkspacePublicFile->getAccount()) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS);
        }

        if (!$this->can(6, $user)) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS);
        }

        $WorkspacePublicFile->setDeletedAt(null);

        $em = $this->getDoctrine()->getManager();
        $em->persist($WorkspacePublicFile);
        $em->flush();

        return $this->getResponse()->success(['published_file' => $WorkspacePublicFile->getId()]);
    }


    public function downloadAction(S3ClientFactory $s3ClientFactory, Request $request, $prefix, $filename)
    {
        if (!$request->isMethod('GET')) {
            return $this->getResponse()->error(ExceptionMessage::NOT_ALLOWED_METHOD, 401);
        }

        $file = $this->getDoctrine()->getRepository('App:WorkspacePublicFile')->findOneBy(['publicUrl' => $prefix . '/' . $filename, 'deletedAt' => null]);

        if (!$file) {
            return $this->getResponse()->error(ExceptionMessage::NOT_FOUND, 404);

        }

        $client = $s3ClientFactory->getClient();
        $bucket = $this->getParameter('aws_bucket_name');
        $prefix = $this->getParameter('aws_workspace_public_files_folder');

        try {
            $result = $client->getObject([
                'Bucket' => $bucket,
                'Key'    => $prefix . '/' . $file->getServerFilename(),
            ]);

            return new Response($result['Body'], 200, [
                'Content-Type'        => $result['ContentType'],
                'Content-Disposition' => sprintf(
                    'attachment; filename="%s"',
                    $file->getOriginalFilename()
                ),
            ]);
        } catch (S3Exception $e) {
            $this->createNotFoundException('File not found');
        }
    }

    public function updateDescriptionAction(WorkspacePublicFile $workspacePublicFile)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }
        $user = $this->user();

        if (!$this->can(6, $user)) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS);
        }

        $account = $this->account();

        if ($account != $workspacePublicFile->getAccount()) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS);
        }

        $newDescription = $this->getRequest()->param('description');

        $em = $this->getDoctrine()->getManager();

        $workspacePublicFile->setDescription($newDescription);

        $em->persist($workspacePublicFile);
        $em->flush();

        return $this->getResponse()->success(['updated_description_file' => $workspacePublicFile->getId()]);
    }
}
