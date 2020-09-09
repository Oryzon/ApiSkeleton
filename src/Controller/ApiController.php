<?php

namespace App\Controller;

use App\Entity\HttpCode;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class ApiController extends AbstractController {

    /**
     * @param $data
     * @param $group
     * @param SerializerInterface $serializer
     *
     * @return Response
     */
    public function __serializer($data, $group, SerializerInterface $serializer) {
        $serialContext = SerializationContext::create()->setGroups($group);
        $dataSerialized = $serializer->serialize($data,'json', $serialContext);

        return new Response($dataSerialized, HttpCode::OK, [
            'Content-Type' => 'application/json'
        ]);
    }

    /**
     * @param $message
     * @param int $statusCode
     *
     * @return Response
     */
    public function __serializeError($message, $statusCode = HttpCode::UNPROCESSABLE_ENTITY) {
        $data = [
            'status' => $statusCode,
            'message' => $message
        ];

        $dataSerialized = json_encode($data);

        return new Response($dataSerialized, $statusCode, [
            'Content-Type' => 'application/json'
        ]);
    }

    /**
     * @param $data
     * @param int $statusCode
     *
     * @return Response
     */
    public function __serializeSuccess($data, $statusCode = HttpCode::OK) {
        if (is_array($data)) {
            $dataSerialized = json_encode($data);
        } else {
            $dataTmp = [
                'status' => $statusCode,
                'message' => $data
            ];

            $dataSerialized = json_encode($dataTmp);
        }

        return new Response($dataSerialized, $statusCode, [
            'Content-Type' => 'application/json'
        ]);
    }

    /**
     * @param $object
     * @param $propertyToCheck
     *
     * @return bool|Response
     */
    public function __checkProperty($object, $propertyToCheck) {
        if (is_array($propertyToCheck)) {
            $error = 0;

            foreach ($propertyToCheck as $property) {
                !property_exists($object, $property)
                    ? $error++ : '';

                if ($error) {
                    return $this->__serializeError(ucfirst($property) . ' is required.');
                }
            }

            return true;
        } else {
            return !property_exists($object, $propertyToCheck)
                ? $this->__serializeError(ucfirst($propertyToCheck) . ' is required.')
                : true;
        }
    }
}