<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Projects extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->library('session');
        $this->load->model('Project_model');
        $this->load->model('User_model');
        $this->load->model('Questionnaire_model');
        $this->load->library('form_validation');
        $this->load->helper('date_validation'); // Carregar helper de validação de datas
        $this->check_auth();
    }

    public function index() {
        $data['title'] = 'Projetos - SXData';
        $data['projects'] = $this->Project_model->get_all_with_stats();
        
        $this->load->view('admin/header', $data);
        $this->load->view('admin/projects/index', $data);
        $this->load->view('admin/footer');
    }

    public function create() {
        if ($this->input->post()) {
            // Configurar regras de validação básicas
            $this->form_validation->set_rules('name', 'Nome do Projeto', 'required|max_length[200]');
            $this->form_validation->set_rules('description', 'Descrição', 'max_length[1000]');
            $this->form_validation->set_rules('client_name', 'Nome do Cliente', 'max_length[200]');
            $this->form_validation->set_rules('budget', 'Orçamento', 'numeric');
            
            // Validação personalizada usando JavaScript (mais confiável)
            $validation_errors = $this->_validate_project_data();
            
            if (empty($validation_errors) && $this->form_validation->run()) {
                $project_data = array(
                    'name' => $this->input->post('name'),
                    'description' => $this->input->post('description'),
                    'created_by' => $this->session->userdata('admin_id'),
                    'status' => $this->input->post('status') ?: 'active',
                    'start_date' => $this->input->post('start_date') ?: NULL,
                    'end_date' => $this->input->post('end_date') ?: NULL,
                    'budget' => $this->input->post('budget') ?: NULL,
                    'client_name' => $this->input->post('client_name') ?: NULL
                );

                $project_id = $this->Project_model->create($project_data);

                if ($project_id) {
                    // Salvar clientes vinculados
                    $client_ids = $this->input->post('client_ids');
                    $this->_save_project_clients($project_id, $client_ids);

                    $this->session->set_flashdata('success', 'Projeto criado com sucesso!');
                    redirect('projects');
                } else {
                    $data['error'] = 'Erro ao criar projeto.';
                }
            } else {
                // Exibir erros de validação customizada
                if (!empty($validation_errors)) {
                    $data['validation_errors'] = $validation_errors;
                }
            }
        }

        $data['title'] = 'Criar Projeto - SXData';
        $data['clients'] = $this->User_model->get_by_role('cliente');
        $data['selected_client_ids'] = $this->input->post('client_ids') ?: array();

        $this->load->view('admin/header', $data);
        $this->load->view('admin/projects/create', $data);
        $this->load->view('admin/footer');
    }

    public function edit($id) {
        $project = $this->Project_model->get_by_id($id);
        if (!$project) {
            show_404();
        }

        if ($this->input->post()) {
            // Configurar regras de validação básicas
            $this->form_validation->set_rules('name', 'Nome do Projeto', 'required|max_length[200]');
            $this->form_validation->set_rules('description', 'Descrição', 'max_length[1000]');
            $this->form_validation->set_rules('client_name', 'Nome do Cliente', 'max_length[200]');
            $this->form_validation->set_rules('budget', 'Orçamento', 'numeric');
            
            // Validação personalizada
            $validation_errors = $this->_validate_project_data();
            
            if (empty($validation_errors) && $this->form_validation->run()) {
                $project_data = array(
                    'name' => $this->input->post('name'),
                    'description' => $this->input->post('description'),
                    'status' => $this->input->post('status'),
                    'start_date' => $this->input->post('start_date') ?: NULL,
                    'end_date' => $this->input->post('end_date') ?: NULL,
                    'budget' => $this->input->post('budget') ?: NULL,
                    'client_name' => $this->input->post('client_name') ?: NULL
                );

                if ($this->Project_model->update($id, $project_data)) {
                    // Salvar clientes vinculados
                    $client_ids = $this->input->post('client_ids');
                    $this->_save_project_clients($id, $client_ids);

                    $this->session->set_flashdata('success', 'Projeto atualizado com sucesso!');
                    redirect('projects');
                } else {
                    $data['error'] = 'Erro ao atualizar projeto.';
                }
            } else {
                if (!empty($validation_errors)) {
                    $data['validation_errors'] = $validation_errors;
                }
            }
        }

        $data['title'] = 'Editar Projeto - SXData';
        $data['project'] = $project;
        $data['questionnaires'] = $this->Questionnaire_model->get_by_project($id);
        $data['clients'] = $this->User_model->get_by_role('cliente');
        $data['selected_client_ids'] = $this->_get_project_client_ids($id);

        $this->load->view('admin/header', $data);
        $this->load->view('admin/projects/edit', $data);
        $this->load->view('admin/footer');
    }

    public function view($id) {
        $project = $this->Project_model->get_by_id($id);
        if (!$project) {
            show_404();
        }

        $data['title'] = $project->name . ' - SXData';
        $data['project'] = $project;
        $data['questionnaires'] = $this->Questionnaire_model->get_by_project($id);
        $data['project_stats'] = $this->Project_model->get_project_stats($id);
        
        $this->load->view('admin/header', $data);
        $this->load->view('admin/projects/view', $data);
        $this->load->view('admin/footer');
    }

    public function delete($id) {
        $project = $this->Project_model->get_by_id($id);
        if (!$project) {
            show_404();
        }

        // Verificar se tem questionários vinculados
        $questionnaires_count = $this->Questionnaire_model->count_by_project($id);
        
        if ($questionnaires_count > 0) {
            $this->session->set_flashdata('error', 
                'Não é possível excluir este projeto pois existem ' . $questionnaires_count . 
                ' questionários vinculados a ele. Remova os questionários primeiro ou altere o status do projeto.');
        } else {
            $this->load->model('Lixeira_model');
            if ($this->Lixeira_model->disponivel()) {
                try {
                    $this->Lixeira_model->excluir_projeto($id, $this->session->userdata('admin_id'));
                    $this->session->set_flashdata('success', 'Projeto excluído. Ele pode ser restaurado em Controle e segurança > Lixeira.');
                } catch (Exception $e) {
                    $this->session->set_flashdata('error', 'Erro ao excluir projeto: ' . html_escape($e->getMessage()));
                }
            } elseif ($this->Project_model->delete($id)) {
                $this->session->set_flashdata('success', 'Projeto excluído com sucesso!');
            } else {
                $this->session->set_flashdata('error', 'Erro ao excluir projeto.');
            }
        }
        
        redirect('projects');
    }

    public function duplicate($id) {
        $original = $this->Project_model->get_by_id($id);
        if (!$original) {
            show_404();
        }

        $new_data = array(
            'name' => $original->name . ' (Cópia)',
            'description' => $original->description,
            'created_by' => $this->session->userdata('admin_id'),
            'status' => 'active',
            'start_date' => NULL,
            'end_date' => NULL,
            'budget' => $original->budget,
            'client_name' => $original->client_name
        );

        $new_id = $this->Project_model->create($new_data);

        if ($new_id) {
            $this->session->set_flashdata('success', 'Projeto duplicado com sucesso!');
            redirect('projects/edit/' . $new_id);
        } else {
            $this->session->set_flashdata('error', 'Erro ao duplicar projeto.');
            redirect('projects');
        }
    }

    public function get_projects_json() {
        // Para uso em AJAX/API
        $projects = $this->Project_model->get_active();
        
        header('Content-Type: application/json');
        echo json_encode($projects);
    }

    /**
     * Validação customizada dos dados do projeto
     * 
     * @return array Array de erros (vazio se válido)
     */
    private function _validate_project_data() {
        $errors = [];
        
        $start_date = $this->input->post('start_date');
        $end_date = $this->input->post('end_date');
        $budget = $this->input->post('budget');
        
        // Validar datas usando o helper
        if (!empty($start_date)) {
            if (!validate_date_format($start_date)) {
                $errors['start_date'] = 'Data de início inválida. Use o formato correto.';
            } elseif (!validate_date_range($start_date)) {
                $errors['start_date'] = 'Data de início deve estar entre 2020 e 2050.';
            }
        }
        
        if (!empty($end_date)) {
            if (!validate_date_format($end_date)) {
                $errors['end_date'] = 'Data de fim inválida. Use o formato correto.';
            } elseif (!validate_date_range($end_date)) {
                $errors['end_date'] = 'Data de fim deve estar entre 2020 e 2050.';
            }
        }
        
        // Validar se data de fim é posterior à data de início
        if (!empty($start_date) && !empty($end_date)) {
            if (!isset($errors['start_date']) && !isset($errors['end_date'])) {
                if (!validate_end_date_after_start($end_date, $start_date)) {
                    $errors['end_date'] = 'A data de fim deve ser posterior à data de início.';
                }
            }
        }
        
        // Validar orçamento
        if (!empty($budget)) {
            if (!is_numeric($budget) || $budget < 0) {
                $errors['budget'] = 'Orçamento deve ser um valor numérico positivo.';
            }
        }
        
        return $errors;
    }

    private function _save_project_clients($project_id, $client_ids) {
        $this->db->where('project_id', $project_id)->delete('project_clients');
        if (!empty($client_ids) && is_array($client_ids)) {
            foreach ($client_ids as $uid) {
                $this->db->insert('project_clients', array(
                    'project_id' => $project_id,
                    'user_id' => (int) $uid,
                    'created_at' => date('Y-m-d H:i:s'),
                ));
            }
        }
    }

    private function _get_project_client_ids($project_id) {
        $rows = $this->db->select('user_id')->where('project_id', $project_id)->get('project_clients')->result();
        return array_map(function($r) { return (int) $r->user_id; }, $rows);
    }

    private function check_auth() {
        if (!$this->session->userdata('admin_logged_in')) {
            redirect('auth/login');
        }

        // Cliente não acessa telas administrativas, nem pela URL
        exigir_equipe();
    }
}