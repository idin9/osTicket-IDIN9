<?php

include_once INCLUDE_DIR.'class.api.php';
include_once INCLUDE_DIR.'class.faq.php';
include_once INCLUDE_DIR.'class.category.php';

class KbApiController extends ApiController {

    function getRequestStructure($format, $data=null) {
        return array(
            'question', 'answer', 'keywords', 'notes',
            'category_id', 'ispublished', 'topics',
            'name', 'description', 'ispublic', 'pid',
        );
    }

    /*
     * Permission helpers — fall back to ticket permissions so existing
     * API keys work with KB without reconfiguration.
     */
    private function canReadKb($key) {
        return $key->canReadFaq() || $key->canReadTickets();
    }

    private function canManageKb($key) {
        return $key->canManageFaq() || $key->canUpdateTickets();
    }

    /*
     * ===================== FAQ Endpoints =====================
     */

    function listFaqs() {
        if (!($key = $this->requireApiKey()) || !$this->canReadKb($key))
            return $this->exerr(401, __('API key not authorized'));

        $faqs = FAQ::objects()->order_by('question');

        $results = array();
        foreach ($faqs as $f) {
            $results[] = $this->faqToArray($f);
        }

        $data = array('faqs' => $results, 'count' => count($results));
        Http::response(200, json_encode($data, JSON_PRETTY_PRINT), 'application/json');
        exit();
    }

    function readFaq($id, $format) {
        if (!($key = $this->requireApiKey()) || !$this->canReadKb($key))
            return $this->exerr(401, __('API key not authorized'));

        if (!($faq = FAQ::lookup($id)))
            return $this->exerr(404, __('FAQ article not found'));

        $data = $this->faqToArray($faq);

        if ($format === 'json') {
            Http::response(200, json_encode($data, JSON_PRETTY_PRINT), 'application/json');
        } elseif ($format === 'xml') {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n<faq>\n";
            $xml .= $this->arrayToXml($data, 1);
            $xml .= '</faq>';
            Http::response(200, $xml, 'text/xml');
        }
        exit();
    }

    function createFaq($format) {
        if (!($key = $this->requireApiKey()) || !$this->canManageKb($key))
            return $this->exerr(401, __('API key not authorized'));

        global $thisstaff;
        $thisstaff = $key->getStaff();
        if (!$thisstaff)
            return $this->exerr(401,
                __('API key must be associated with a staff member to create FAQ articles'));

        $data = $this->getRequest($format);

        if (!isset($data['question']) || !$data['question'])
            return $this->exerr(400, __('Question is required'));
        if (!isset($data['answer']) || !$data['answer'])
            return $this->exerr(400, __('Answer is required'));
        if (!isset($data['category_id']) || !$data['category_id'])
            return $this->exerr(400, __('Category is required'));

        $vars = array(
            'question' => $data['question'],
            'answer' => $data['answer'],
            'category_id' => $data['category_id'],
            'ispublished' => $data['ispublished'] ?? FAQ::VISIBILITY_PUBLIC,
            'notes' => $data['notes'] ?? '',
            'keywords' => $data['keywords'] ?? '',
            'topics' => $data['topics'] ?? array(),
        );

        if (!($category = Category::lookup($vars['category_id'])))
            return $this->exerr(400, __('Category not found'));

        $errors = array();
        $faq = FAQ::create($vars);
        $faq->question = $vars['question'];
        $faq->answer = Format::sanitize($vars['answer']);
        $faq->category = $category;
        $faq->ispublished = $vars['ispublished'];
        $faq->notes = Format::sanitize($vars['notes']);
        $faq->keywords = trim($vars['keywords'] ?? '') ?: ' ';

        if (!$faq->save())
            return $this->exerr(500, __('Unable to create FAQ article'));

        $faq->updateTopics($vars['topics']);

        $this->response(201, json_encode(array(
            'faq_id' => $faq->getId(),
            'question' => $faq->getQuestion(),
        ), JSON_PRETTY_PRINT));
    }

    function updateFaq($id, $format) {
        if (!($key = $this->requireApiKey()) || !$this->canManageKb($key))
            return $this->exerr(401, __('API key not authorized'));

        global $thisstaff;
        $thisstaff = $key->getStaff();
        if (!$thisstaff)
            return $this->exerr(401,
                __('API key must be associated with a staff member to update FAQ articles'));

        if (!($faq = FAQ::lookup($id)))
            return $this->exerr(404, __('FAQ article not found'));

        $data = $this->getRequest($format, false);

        // Build vars array from request data, falling back to existing values
        $vars = array(
            'id' => $faq->getId(),
            'question' => $data['question'] ?? $faq->getQuestion(),
            'answer' => $data['answer'] ?? $faq->getAnswer(),
            'category_id' => $data['category_id'] ?? $faq->getCategoryId(),
            'ispublished' => isset($data['ispublished']) ? $data['ispublished'] : $faq->ispublished,
            'notes' => $data['notes'] ?? $faq->getNotes(),
            'keywords' => $data['keywords'] ?? $faq->getKeywords(),
            'topics' => $data['topics'] ?? $faq->getHelpTopicsIds(),
        );

        $errors = array();
        if ($faq->update($vars, $errors)) {
            $this->response(200, json_encode(array(
                'faq_id' => $faq->getId(),
                'question' => $faq->getQuestion(),
            ), JSON_PRETTY_PRINT));
        } else {
            $this->exerr(400, Format::array_implode("\n", "\n", $errors));
        }
    }

    function deleteFaq($id, $format) {
        if (!($key = $this->requireApiKey()) || !$this->canManageKb($key))
            return $this->exerr(401, __('API key not authorized'));

        global $thisstaff;
        $thisstaff = $key->getStaff();
        if (!$thisstaff)
            return $this->exerr(401,
                __('API key must be associated with a staff member to delete FAQ articles'));

        if (!($faq = FAQ::lookup($id)))
            return $this->exerr(404, __('FAQ article not found'));

        if ($faq->delete())
            $this->response(200, json_encode(array('deleted' => $faq->getId())));
        else
            $this->exerr(500, __('Unable to delete FAQ article'));
    }

    /*
     * ===================== Category Endpoints =====================
     */

    function listCategories() {
        if (!($key = $this->requireApiKey()) || !$this->canReadKb($key))
            return $this->exerr(401, __('API key not authorized'));

        $categories = Category::objects()->order_by('name');

        $results = array();
        foreach ($categories as $c) {
            $results[] = $this->categoryToArray($c);
        }

        $data = array('categories' => $results, 'count' => count($results));
        Http::response(200, json_encode($data, JSON_PRETTY_PRINT), 'application/json');
        exit();
    }

    function readCategory($id, $format) {
        if (!($key = $this->requireApiKey()) || !$this->canReadKb($key))
            return $this->exerr(401, __('API key not authorized'));

        if (!($category = Category::lookup($id)))
            return $this->exerr(404, __('Category not found'));

        $data = $this->categoryToArray($category);

        // Include child categories
        $children = array();
        foreach ($category->getSubCategories() as $child) {
            $children[] = $this->categoryToArray($child);
        }
        $data['children'] = $children;

        // Include FAQs in this category
        $faqs = array();
        foreach ($category->faqs->order_by('question') as $f) {
            $faqs[] = $this->faqToArray($f);
        }
        $data['faqs'] = $faqs;

        if ($format === 'json') {
            Http::response(200, json_encode($data, JSON_PRETTY_PRINT), 'application/json');
        } elseif ($format === 'xml') {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n<category>\n";
            $xml .= $this->arrayToXml($data, 1);
            $xml .= '</category>';
            Http::response(200, $xml, 'text/xml');
        }
        exit();
    }

    function createCategory($format) {
        if (!($key = $this->requireApiKey()) || !$this->canManageKb($key))
            return $this->exerr(401, __('API key not authorized'));

        global $thisstaff;
        $thisstaff = $key->getStaff();
        if (!$thisstaff)
            return $this->exerr(401,
                __('API key must be associated with a staff member to create categories'));

        $data = $this->getRequest($format);

        if (!isset($data['name']) || !$data['name'])
            return $this->exerr(400, __('Category name is required'));

        $category = Category::create(array(
            'name' => trim($data['name']),
            'description' => $data['description'] ?? '',
            'ispublic' => $data['ispublic'] ?? Category::VISIBILITY_PUBLIC,
            'category_pid' => $data['pid'] ?? 0,
            'notes' => $data['notes'] ?? '',
        ));

        if (!$category->save())
            return $this->exerr(500, __('Unable to create category'));

        $this->response(201, json_encode(array(
            'category_id' => $category->getId(),
            'name' => $category->getName(),
        ), JSON_PRETTY_PRINT));
    }

    function updateCategory($id, $format) {
        if (!($key = $this->requireApiKey()) || !$this->canManageKb($key))
            return $this->exerr(401, __('API key not authorized'));

        global $thisstaff;
        $thisstaff = $key->getStaff();
        if (!$thisstaff)
            return $this->exerr(401,
                __('API key must be associated with a staff member to update categories'));

        if (!($category = Category::lookup($id)))
            return $this->exerr(404, __('Category not found'));

        $data = $this->getRequest($format, false);

        $vars = array(
            'id' => $category->getId(),
            'name' => $data['name'] ?? $category->getName(),
            'description' => $data['description'] ?? $category->getDescription(),
            'ispublic' => isset($data['ispublic']) ? $data['ispublic'] : $category->ispublic,
            'pid' => isset($data['pid']) ? $data['pid'] : $category->category_pid,
            'notes' => $data['notes'] ?? $category->getNotes(),
        );

        $errors = array();
        if ($category->update($vars, $errors)) {
            $this->response(200, json_encode(array(
                'category_id' => $category->getId(),
                'name' => $category->getName(),
            ), JSON_PRETTY_PRINT));
        } else {
            $this->exerr(400, Format::array_implode("\n", "\n", $errors));
        }
    }

    function deleteCategory($id, $format) {
        if (!($key = $this->requireApiKey()) || !$this->canManageKb($key))
            return $this->exerr(401, __('API key not authorized'));

        global $thisstaff;
        $thisstaff = $key->getStaff();
        if (!$thisstaff)
            return $this->exerr(401,
                __('API key must be associated with a staff member to delete categories'));

        if (!($category = Category::lookup($id)))
            return $this->exerr(404, __('Category not found'));

        if ($category->delete())
            $this->response(200, json_encode(array('deleted' => $category->getId())));
        else
            $this->exerr(500, __('Unable to delete category. It may contain FAQ articles or subcategories.'));
    }

    /*
     * ===================== Helper Methods =====================
     */

    private function faqToArray($faq) {
        return array(
            'faq_id' => $faq->getId(),
            'question' => $faq->getQuestion(),
            'answer' => $faq->getAnswer(),
            'category_id' => $faq->getCategoryId(),
            'category' => $faq->getCategory() ? $faq->getCategory()->getName() : null,
            'ispublished' => (int) $faq->ispublished,
            'visibility' => $faq->getVisibilityDescription(),
            'keywords' => $faq->getKeywords(),
            'notes' => $faq->getNotes(),
            'num_attachments' => $faq->getNumAttachments(),
            'topics' => $faq->getHelpTopicsIds(),
            'created' => $faq->getCreateDate(),
            'updated' => $faq->getUpdateDate(),
        );
    }

    private function categoryToArray($category) {
        return array(
            'category_id' => $category->getId(),
            'name' => $category->getName(),
            'description' => $category->getDescription(),
            'ispublic' => (int) $category->ispublic,
            'visibility' => $category->getVisibilityDescription(),
            'parent_id' => $category->category_pid ? (int) $category->category_pid : null,
            'notes' => $category->getNotes(),
            'num_faqs' => $category->getNumFAQs(),
            'created' => $category->getCreateDate(),
            'updated' => $category->getUpdateDate(),
        );
    }

    /*
     * XML helper (mirrors pattern from TicketApiController)
     */
    protected function arrayToXml($data, $indent = 0) {
        $xml = '';
        $pad = str_repeat('  ', $indent);
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $xml .= "$pad<{$key}>\n";
                $xml .= $this->arrayToXml($value, $indent + 1);
                $xml .= "$pad</{$key}>\n";
            } elseif (is_bool($value)) {
                $xml .= "$pad<{$key}>" . ($value ? 'true' : 'false') . "</{$key}>\n";
            } elseif ($value === null) {
                $xml .= "$pad<{$key} />\n";
            } else {
                $xml .= "$pad<{$key}>" . htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</{$key}>\n";
            }
        }
        return $xml;
    }
}
