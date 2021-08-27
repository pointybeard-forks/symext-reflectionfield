<?php

declare(strict_types=1);

/*
 * This file is part of the "Reflection Field for Symphony CMS" repository.
 *
 * Copyright 2008-2017 Rowan Lewis, Symphonists
 * Copyright 2021 Alannah Kearney <hi@alannahkearney.com>
 *
 * For the full copyright and license information, please view the LICENCE
 * file that was distributed with this source code.
 */

if (!file_exists(__DIR__.'/vendor/autoload.php')) {
    throw new Exception(sprintf('Could not find composer autoload file %s. Did you run `composer update` in %s?', __DIR__.'/vendor/autoload.php', __DIR__));
}

require_once __DIR__.'/vendor/autoload.php';

use pointybeard\Symphony\Extended;

// Check if the class already exists before declaring it again.
if (false == class_exists('\\extension_reflectionfield')) {
    final class extension_reflectionfield extends Extended\AbstractExtension
    {
    /*-------------------------------------------------------------------------
        Definition:
    -------------------------------------------------------------------------*/

        protected static $fields = [];

        private $tableName = 'tbl_fields_reflection';

        public function uninstall()
        {
            parent::uninstall();

            return Symphony::Database()->query("DROP TABLE IF EXISTS `{$this->tableName}`");
        }

        public function install()
        {
            parent::install();

            return Symphony::Database()->query(
                "CREATE TABLE IF NOT EXISTS `{$this->tableName}` (
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `field_id` INT(11) UNSIGNED NOT NULL,
                    `xsltfile` VARCHAR(255) DEFAULT NULL,
                    `expression` VARCHAR(255) DEFAULT NULL,
                    `formatter` VARCHAR(255) DEFAULT NULL,
                    `override` ENUM('yes', 'no') DEFAULT 'no',
                    `hide` ENUM('yes', 'no') DEFAULT 'no',
                    `fetch_associated_counts` ENUM('yes','no') DEFAULT 'no',
                    PRIMARY KEY (`id`),
                    KEY `field_id` (`field_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
            );
        }

        public function update($previousVersion = false)
        {
            // Update 1.0 installations
            if (version_compare($previousVersion, '1.1', '<')) {
                Symphony::Database()->query('ALTER TABLE `tbl_fields_reflection` ADD `xsltfile` VARCHAR(255) DEFAULT NULL');
            }

            // Update 1.1 installations
            if (version_compare($previousVersion, '1.2', '<')) {
                Symphony::Database()->query("ALTER TABLE `tbl_fields_reflection` ADD `fetch_associated_counts` ENUM('yes','no') DEFAULT 'no'");
            }

            return true;
        }

        public function getSubscribedDelegates()
        {
            return [
                [
                    'page' => '/publish/new/',
                    'delegate' => 'EntryPostCreate',
                    'callback' => 'compileBackendFields',
                ],
                [
                    'page' => '/publish/edit/',
                    'delegate' => 'EntryPostEdit',
                    'callback' => 'compileBackendFields',
                ],
                [
                    'page' => '/xmlimporter/importers/run/',
                    'delegate' => 'XMLImporterEntryPostCreate',
                    'callback' => 'compileBackendFields',
                ],
                [
                    'page' => '/xmlimporter/importers/run/',
                    'delegate' => 'XMLImporterEntryPostEdit',
                    'callback' => 'compileBackendFields',
                ],
                [
                    'page' => '/frontend/',
                    'delegate' => 'EventPostSaveFilter',
                    'callback' => 'compileFrontendFields',
                ],
            ];
        }

        /*-------------------------------------------------------------------------
            Utilities:
        -------------------------------------------------------------------------*/

        public function getXPath($entry, $template = null, $fetch_associated_counts = null, $handle = 'reflection-field'): DOMXPath
        {
            $xml = $this->buildXML($handle, $entry);
            $dom = new DOMDocument();
            $dom->strictErrorChecking = false;
            $dom->loadXML($xml->generate(true));

            // Transform XML if template is provided
            if (!empty($template)) {
                $template = UTILITIES.'/'.preg_replace(['%/+%', '%(^|/)../%'], '/', $template);

                if (file_exists($template)) {
                    $xslt = new DomDocument();
                    $xslt->load($template);

                    $xslp = new XsltProcessor();
                    $xslp->importStyleSheet($xslt);

                    $temp = $xslp->transformToDoc($dom);

                    if ($temp instanceof DOMDocument) {
                        $dom = $temp;
                    }
                }
            }

            // Create xPath object
            $xpath = new DOMXPath($dom);

            if (version_compare(phpversion(), '5.3', '>=')) {
                $xpath->registerPhpFunctions();
            }

            return $xpath;
        }

        private function buildXML($handle = 'reflection-field', $entry): XMLElement
        {
            $xml = new XMLElement('data');

            $xml->appendChild($this->buildParams());
            $xml->appendChild($this->buildEntry($handle, $entry));

            return $xml;
        }

        private function buildParams(): XMLElement
        {
            $xml = new XMLElement('params');

            $upload_size_php = ini_size_to_bytes(ini_get('upload_max_filesize'));
            $upload_size_sym = Symphony::Configuration()->get('max_upload_size', 'admin');
            $date = new DateTime();

            $params = [
                'today' => $date->format('Y-m-d'),
                'current-time' => $date->format('H:i'),
                'this-year' => $date->format('Y'),
                'this-month' => $date->format('m'),
                'this-day' => $date->format('d'),
                'timezone' => $date->format('P'),
                'website-name' => General::sanitize(Symphony::Configuration()->get('sitename', 'general')),
                'root' => URL,
                'workspace' => URL.'/workspace',
                'http-host' => HTTP_HOST,
                'upload-limit' => min($upload_size_php, $upload_size_sym),
                'symphony-version' => Symphony::Configuration()->get('version', 'symphony'),
            ];

            foreach ($params as $name => $value) {
                $xml->appendChild(
                    new XMLElement($name, $value)
                );
            }

            return $xml;
        }

        private function buildEntry(string $handle = 'reflection-field', $entry): XMLElement
        {
            $xml = new XMLElement($handle);
            $data = $entry->getData();

            // Section context
            $section_data = SectionManager::fetch($entry->get('section_id'));
            $section = new XMLElement('section', General::sanitize($section_data->get('name')));
            $section->setAttribute('id', $entry->get('section_id'));
            $section->setAttribute('handle', $section_data->get('handle'));

            // Entry data
            $entry_xml = new XMLElement('entry');
            $entry_xml->setAttribute('id', $entry->get('id'));

            // Add associated entry counts
            if ('yes' == $fetch_associated_counts) {
                $associated = $entry->fetchAllAssociatedEntryCounts();

                if (is_array($associated) and !empty($associated)) {
                    foreach ($associated as $section_id => $count) {
                        $section_data = SectionManager::fetch($section_id);

                        if (($section_data instanceof Section) === false) {
                            continue;
                        }

                        $entry_xml->setAttribute($section_data->get('handle'), (string) $count);
                    }
                }
            }

            // Add field data
            foreach ($data as $field_id => $values) {
                if (empty($field_id)) {
                    continue;
                }

                $field = FieldManager::fetch($field_id);
                $field->appendFormattedElement($entry_xml, $values, false, null, $entry->get('id'));
            }

            // Add entry system dates
            $entry_xml->appendChild($this->buildSystemDate($entry));

            // Append nodes
            $xml->appendChild($section);
            $xml->appendChild($entry_xml);

            return $xml;
        }

        private function buildSystemDate($entry): XMLElement
        {
            $xml = new XMLElement('system-date');

            $created = General::createXMLDateObject(
                DateTimeObj::get('U', $entry->get('creation_date')),
                'created'
            );
            $modified = General::createXMLDateObject(
                DateTimeObj::get('U', $entry->get('modification_date')),
                'modified'
            );

            $xml->appendChild($created);
            $xml->appendChild($modified);

            return $xml;
        }

        /*-------------------------------------------------------------------------
            Fields:
        -------------------------------------------------------------------------*/

        public function registerField(Field $field): void
        {
            self::$fields[$field->get('id')] = $field;
        }

        public static function deregisterFields(): void
        {
            self::$fields = [];
        }

        public function compileBackendFields($context): void
        {
            if (empty(self::$fields)) {
                self::$fields = $context['section']->fetchFields('reflection');
            }

            foreach (self::$fields as $field) {
                $field->compile($context['entry']);
            }
        }

        public function compileFrontendFields($context): void
        {
            foreach (self::$fields as $field) {
                $field->compile($context['entry']);
            }
        }
    }
}
