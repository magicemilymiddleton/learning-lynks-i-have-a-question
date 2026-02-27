<?php

if (!defined('ABSPATH')) {
    exit;
}

function wp_cep_send_email() {
    if (!isset($_POST['message'])) {
        wp_send_json_error(['message' => 'No message provided.']);
    }

    $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : 'Unknown';
    $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : 'User';    
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : 'No email provided';
    $question_text = isset($_POST['question_text']) ? sanitize_text_field($_POST['question_text']) : 'Question not found';
    $selected_answer = isset($_POST['selected_answer']) ? sanitize_text_field($_POST['selected_answer']) : 'No answer selected';
    $message = sanitize_textarea_field($_POST['message']);

    // Get the Quiz ID from the request
    $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;
    error_log("Quiz ID from AJAX: " . print_r($quiz_id, true));

    // Get the Parent Lesson ID from postmeta (_llms_lesson_id)
    $lesson_id = $quiz_id ? get_post_meta($quiz_id, '_llms_lesson_id', true) : 0;
    error_log("Lesson ID from Quiz: " . print_r($lesson_id, true));

    // Get the Lesson Title
    $lesson_name = $lesson_id ? get_the_title($lesson_id) : 'Lesson not found';

    // Get the Parent Course ID from the lesson (_llms_parent_course)
    $course_id = $lesson_id ? get_post_meta($lesson_id, '_llms_parent_course', true) : 0;
    error_log("Course ID from Lesson: " . print_r($course_id, true));

    // Get the Course Title
    $course_name = $course_id ? get_the_title($course_id) : 'Course not found';

    // Step 1: Retrieve Instructor Data from `_llms_instructors`
    $instructor_data = get_post_meta($course_id, '_llms_instructors', true);
    error_log("Raw Instructor Data: " . print_r($instructor_data, true));

    // Step 2: Extract the Instructor ID
    $instructor_id = 0;
    if ($instructor_data) {
        $decoded_data = maybe_unserialize($instructor_data);
        if (is_array($decoded_data) && isset($decoded_data[0]['id'])) {
            $instructor_id = intval($decoded_data[0]['id']);
        }
    }
    error_log("Instructor ID: " . print_r($instructor_id, true));

    // Step 3: Get the Instructor's Email
    $instructor_email = $instructor_id ? get_the_author_meta('user_email', $instructor_id) : '';
    error_log("Instructor Email: " . print_r($instructor_email, true));

    // Step 4: Set the Email Recipient
    $to = $instructor_email ? $instructor_email : 'admin@learninglynks.com';
    error_log("Email will be sent to: " . print_r($to, true));

    // Find the specific question based on the question text
    $question_id = 0;
    $all_choices = [];
    $correct_answer = 'Not found';
    $question_type = '';
    
    // Get all questions associated with this quiz
    global $wpdb;
    $questions = $wpdb->get_results($wpdb->prepare(
        "SELECT p.ID, p.post_title, p.post_content
        FROM {$wpdb->posts} p 
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
        WHERE pm.meta_key = '_llms_parent_id' 
        AND pm.meta_value = %d 
        AND p.post_type = 'llms_question'",
        $quiz_id
    ));
    
    error_log("Found " . count($questions) . " questions for quiz");
    foreach ($questions as $q) {
        error_log("Question ID: " . $q->ID . " Title: " . $q->post_title);
    }
    
    // Normalize the question text for matching
    $normalized_question = str_replace('…', '...', $question_text);
    $normalized_question = preg_replace('/^Question \d+:\s*/i', '', $normalized_question);
    error_log("Normalized question text for matching: " . $normalized_question);
    
    foreach ($questions as $question) {
        $normalized_db_title = str_replace('…', '...', $question->post_title);
        $normalized_db_title = preg_replace('/^Question \d+:\s*/i', '', $normalized_db_title);
        
        error_log("Comparing: '" . $normalized_question . "' with '" . $normalized_db_title . "'");
        
        if ($normalized_db_title === $normalized_question) {
            $question_id = $question->ID;
            error_log("Matched by exact normalized title");
            break;
        }
        
        if (stripos($normalized_db_title, $normalized_question) === 0 || 
            stripos($normalized_question, $normalized_db_title) === 0) {
            $question_id = $question->ID;
            error_log("Matched by starts with");
            break;
        }
        
        if (stripos($normalized_question, $normalized_db_title) !== false || 
            stripos($normalized_db_title, $normalized_question) !== false) {
            $question_id = $question->ID;
            error_log("Matched by contains");
            break;
        }
        
        if (preg_match('/Question (\d+):/i', $question_text, $matches)) {
            $question_number = $matches[1];
            if (preg_match('/Question ' . $question_number . ':/i', $question->post_title)) {
                if (stripos($normalized_question, 'Why') !== false && 
                    stripos($normalized_db_title, 'Why') !== false) {
                    $question_id = $question->ID;
                    error_log("Matched by question number and keyword");
                    break;
                }
            }
        }
    }
    
    error_log("Final Matched Question ID: " . $question_id);
    
    // Get all choices and the correct answer for this question
    if ($question_id) {
        $question_type = get_post_meta($question_id, '_llms_question_type', true);
        error_log("Question Type: " . $question_type);
        
        if ($question_type === 'choice' || $question_type === 'true_false') {
            $choice_metas = $wpdb->get_results($wpdb->prepare(
                "SELECT meta_key, meta_value 
                FROM {$wpdb->postmeta} 
                WHERE post_id = %d 
                AND meta_key LIKE '_llms_choice_%'
                ORDER BY meta_key",
                $question_id
            ));
            
            error_log("Found " . count($choice_metas) . " choices for " . $question_type);
            
            foreach ($choice_metas as $choice_meta) {
                $choice_data = maybe_unserialize($choice_meta->meta_value);
                
                if (is_array($choice_data) && isset($choice_data['choice'])) {
                    $choice_text = $choice_data['choice'];
                    $marker = isset($choice_data['marker']) ? $choice_data['marker'] : '';
                    $is_correct = isset($choice_data['correct']) && $choice_data['correct'];
                    
                    if ($question_type === 'true_false') {
                        $all_choices[] = $choice_text;
                        if ($is_correct) {
                            $correct_answer = $choice_text;
                        }
                    } else {
                        $all_choices[] = $marker . '. ' . $choice_text;
                        if ($is_correct) {
                            $correct_answer = $marker . '. ' . $choice_text;
                            error_log("Found correct answer: " . $correct_answer);
                        }
                    }
                }
            }
            
            if ($question_type === 'true_false' && $correct_answer !== 'Not found') {
                error_log("True/False correct answer: " . $correct_answer);
            }
        } elseif ($question_type === 'blank' || $question_type === 'fill_in_the_blank') {
            $correct_value = get_post_meta($question_id, '_llms_correct_value', true);
            $case_sensitive = get_post_meta($question_id, '_llms_case_sensitive', true);
            
            error_log("Blank correct_value: " . $correct_value);
            error_log("Case sensitive: " . $case_sensitive);
            
            if ($correct_value) {
                $correct_answer = $correct_value;
                $case_note = ($case_sensitive === 'yes') ? ' (case-sensitive)' : ' (case-insensitive)';
                $all_choices[] = '(Fill in the blank - acceptable answer: ' . $correct_answer . $case_note . ')';
            } else {
                $blank_answers = get_post_meta($question_id, '_llms_blank_answers', true);
                error_log("Blank answers meta (fallback): " . print_r($blank_answers, true));
                
                if ($blank_answers) {
                    $answers_array = maybe_unserialize($blank_answers);
                    if (is_array($answers_array) && !empty($answers_array)) {
                        $correct_answer = implode(' OR ', $answers_array);
                        $all_choices[] = '(Fill in the blank - acceptable answers: ' . $correct_answer . ')';
                    }
                }
            }
            
            if ($correct_answer === 'Not found') {
                $all_meta = get_post_meta($question_id);
                error_log("All meta for blank question: " . print_r(array_keys($all_meta), true));
            }
        }
    } else {
        error_log("ERROR: Could not find question ID for matching");
        $debug_info = "Tried to match: '" . $question_text . "' normalized to: '" . $normalized_question . "'";
        error_log($debug_info);
    }
    
    error_log("All Choices: " . print_r($all_choices, true));
    error_log("Correct Answer: " . $correct_answer);

    // Define the reply button BEFORE constructing email body
    $reply_button = '
    <div style="background-color: #ede9fe; border: 2px solid #a78bfa; padding: 20px; margin: 25px 0; border-radius: 8px; text-align: center;">
        <h3 style="color: #5b21b6; font-size: 16px; margin: 0 0 10px 0; font-weight: 600;">💬 How to Reply to This Student</h3>
        <p style="color: #6b21a8; font-size: 14px; margin: 0 0 15px 0; line-height: 1.5;">
            Click the button below to open your email client with the student\'s email address pre-filled. 
            <strong>Please use this button rather than replying directly to this email.</strong>
        </p>
        <a href="mailto:' . $email . '?subject=Re: Your question about ' . esc_attr($course_name) . '" 
           style="display: inline-block; background-color: #667eea; color: #ffffff; padding: 14px 35px; 
           text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px; box-shadow: 0 2px 4px rgba(102, 126, 234, 0.3);">
            📧 Reply to ' . esc_html($first_name) . '
        </a>
        <p style="color: #7c3aed; font-size: 12px; margin: 12px 0 0 0;">
            Student Email: <strong>' . esc_html($email) . '</strong>
        </p>
    </div>';

    // Construct the email body with beautiful inline styling
    $body = '
    <div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #ffffff;">
        
        <!-- Header -->
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
            <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 600;">📚 Student Question from Quiz</h1>
        </div>
        
        <!-- Main Content -->
        <div style="padding: 30px; background-color: #ffffff; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 10px 10px;">
            
            <!-- Student Info Section -->
            <div style="background-color: #f9fafb; border-left: 4px solid #667eea; padding: 20px; margin-bottom: 25px; border-radius: 5px;">
                <h2 style="color: #1f2937; font-size: 18px; margin: 0 0 15px 0; font-weight: 600;">Student Information</h2>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 8px 0; color: #6b7280; font-size: 14px; width: 35%;">Name:</td>
                        <td style="padding: 8px 0; color: #1f2937; font-size: 14px; font-weight: 500;">' . esc_html($first_name . ' ' . $last_name) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Email:</td>
                        <td style="padding: 8px 0; color: #1f2937; font-size: 14px; font-weight: 500;">
                            <a href="mailto:' . esc_attr($email) . '" style="color: #667eea; text-decoration: none;">' . esc_html($email) . '</a>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Reply Button -->
            ' . $reply_button . '
            
            <!-- Student Question Section -->
            <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 20px; margin-bottom: 25px; border-radius: 5px;">
                <h2 style="color: #92400e; font-size: 18px; margin: 0 0 10px 0; font-weight: 600;">❓ Student\'s Question</h2>
                <p style="color: #451a03; font-size: 15px; line-height: 1.6; margin: 0; white-space: pre-wrap;">' . esc_html($message) . '</p>
            </div>
            
            <!-- Course Context Section -->
            <div style="background-color: #f0f9ff; border-left: 4px solid #0ea5e9; padding: 20px; margin-bottom: 25px; border-radius: 5px;">
                <h2 style="color: #075985; font-size: 18px; margin: 0 0 15px 0; font-weight: 600;">📖 Course Context</h2>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 8px 0; color: #6b7280; font-size: 14px; width: 35%;">Course:</td>
                        <td style="padding: 8px 0; color: #1f2937; font-size: 14px; font-weight: 500;">' . esc_html($course_name) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Lesson:</td>
                        <td style="padding: 8px 0; color: #1f2937; font-size: 14px; font-weight: 500;">' . esc_html($lesson_name) . '</td>
                    </tr>
                </table>
            </div>
            
            <!-- Quiz Details Section -->
            <div style="background-color: #f9fafb; border: 1px solid #e5e7eb; padding: 20px; border-radius: 5px;">
                <h2 style="color: #1f2937; font-size: 18px; margin: 0 0 20px 0; font-weight: 600;">📝 Quiz Details</h2>
                
                <!-- Question -->
                <div style="margin-bottom: 20px;">
                    <label style="color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 5px;">Question:</label>
                    <div style="color: #1f2937; font-size: 15px; font-weight: 500; padding: 12px; background-color: #ffffff; border: 1px solid #e5e7eb; border-radius: 5px;">' . esc_html($question_text) . '</div>
                </div>
                
                <!-- All Choices -->
                <div style="margin-bottom: 20px;">
                    <label style="color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 5px;">All Question Choices:</label>
                    <div style="padding: 12px; background-color: #ffffff; border: 1px solid #e5e7eb; border-radius: 5px;">';
    
    if (!empty($all_choices)) {
        foreach ($all_choices as $choice) {
            $body .= '<div style="padding: 6px 0; color: #4b5563; font-size: 14px;">• ' . esc_html($choice) . '</div>';
        }
    } else {
        $body .= '<div style="color: #9ca3af; font-size: 14px; font-style: italic;">Not available</div>';
    }
    
    $body .= '
                    </div>
                </div>
                
                <!-- Student Answer -->
                <div style="margin-bottom: 20px;">
                    <label style="color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 5px;">Student\'s Answer:</label>';
    
    // Check if student got it correct
    $student_is_correct = false;
    $normalized_student = strtolower(trim(preg_replace('/^[A-Z]\.\s*/', '', $selected_answer)));
    $normalized_correct = strtolower(trim(preg_replace('/^[A-Z]\.\s*/', '', $correct_answer)));
    
    if ($normalized_student !== 'no answer selected' && $normalized_student !== '') {
        if ($normalized_student === $normalized_correct || 
            strpos($normalized_correct, $normalized_student) !== false ||
            strpos($normalized_student, $normalized_correct) !== false) {
            $student_is_correct = true;
        }
        
        if (strpos($correct_answer, ' OR ') !== false) {
            $acceptable_answers = explode(' OR ', $normalized_correct);
            foreach ($acceptable_answers as $acceptable) {
                if ($normalized_student === trim($acceptable)) {
                    $student_is_correct = true;
                    break;
                }
            }
        }
    }
    
    if ($student_is_correct) {
        $body .= '<div style="padding: 12px; background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 5px; color: #166534; font-size: 14px; font-weight: 500;">✓ ' . esc_html($selected_answer) . '</div>';
    } else {
        $body .= '<div style="padding: 12px; background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 5px; color: #991b1b; font-size: 14px; font-weight: 500;">' . esc_html($selected_answer) . '</div>';
    }
    
    $body .= '
                </div>
                
                <!-- Correct Answer -->
                <div>
                    <label style="color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 5px;">Correct Answer:</label>
                    <div style="padding: 12px; background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 5px; color: #166534; font-size: 14px; font-weight: 500;">✓ ' . esc_html($correct_answer) . '</div>
                </div>
            </div>
            
        </div>
        
        <!-- Footer -->
        <div style="text-align: center; padding: 20px; color: #9ca3af; font-size: 12px;">
            <p style="margin: 5px 0 0 0;">© ' . date('Y') . ' LearningLynks</p>
        </div>
    </div>
    ';

    // Send the email with error handling
    $subject = "📚 Student Question: {$first_name} {$last_name} - {$course_name}";
    
    // Build headers array - removed Reply-To to avoid spam filters
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: LearningLynks Student Support <noreply@learninglynks.com>'
    ];
    
    if (wp_mail($to, $subject, $body, $headers)) {
        error_log("Email sent successfully to instructor: " . $to);
        wp_send_json_success(['message' => 'Email sent successfully!']);
    } else {
        error_log("Email sending failed.");
        wp_send_json_error(['message' => 'Failed to send email.']);
    }
}

add_action('wp_ajax_send_custom_email', 'wp_cep_send_email');
add_action('wp_ajax_nopriv_send_custom_email', 'wp_cep_send_email');