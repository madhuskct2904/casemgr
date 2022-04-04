<?php

namespace App\Command;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImportFixedFieldsCommand extends Command
{
    protected ManagerRegistry $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('importFixedFields')
            ->setDescription('Import CSV file exported from excel with fixed forms values. Use only if you really know what you are doing!!!')
            ->addOption('execute', null, InputOption::VALUE_NONE, 'Execute queries')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $execute = false;

        if ($input->getOption('execute')) {
            $execute = true;
        }

        $connection = $this->doctrine->getManager()->getConnection();

        $allIds = [];

        if (($handle = fopen(__DIR__."/../../../var/fixed-invalid-values.csv", "r")) !== false) {
            while (($data = fgetcsv($handle, 10000, ",")) !== false) {
                if (strlen($data[0]) && strlen($data[2])) {
                    if ($data[1] == '[enter correct value here]') {
                        $output->writeln('/* UNCORRECTED VALUE: '.$data[0].'*/');
                        continue;
                    }

                    if ($data[1] == '"as is"') {
                        $output->writeln('/* LEAVE OLD VALUE (PROBABLY HISTORICAL) : '.$data[0].' */');
                        continue;
                    }

                    if ($data[1] == '') {
                    }

                    $val = str_replace("'", "\'", $data[1]);
                    $ids = $data[2];


                    $sql = "SELECT value FROM forms_values WHERE id in ($ids)";
                    $checkValues = $connection->fetchAllAssociative($sql);

                    foreach ($checkValues as $value) {
                        if ($value['value'] !== $data[0] && $value['value'] !== $data[1]) {
                            $output->writeln('WARNING! OLD VALUE CHANGED!');
                            $output->writeln('VALUE IN DB: '.$value['value']);
                            $output->writeln('WRONG VALUE: '.$data[0]);
                            $output->writeln('FIXED VALUE: '.$data[1]);
                        }
                    }

                    $sql = "UPDATE forms_values SET value='$val' WHERE id in ($ids);";

                    $idsArr = explode(',', $data[2]);
                    $allIds = array_merge($allIds, $idsArr);

                    if ($execute) {
                        $stmt =$connection->prepare($sql);
                        $stmt->execute();
                    }

                    $output->writeln($sql);
                }
            }

            fclose($handle);
        }

//        $duplicates = [];
//
//        foreach($allIds as $id) {
//            if(isset($duplicates[$id])) {
//                $duplicates[$id]++;
//                continue;
//            }
//
//            $duplicates[$id] = 1;
//        }
//
//        foreach($duplicates as $id => $count) {
//            if($count == 1) {
//                $output->writeln($id);
//            }
//        }
    }
}
