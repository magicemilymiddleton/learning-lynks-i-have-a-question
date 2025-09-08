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

    // 🔥 Step 1: Retrieve Instructor Data from `_llms_instructors`
    $instructor_data = get_post_meta($course_id, '_llms_instructors', true);
    error_log("Raw Instructor Data: " . print_r($instructor_data, true));

    // 🔥 Step 2: Extract the Instructor ID
    $instructor_id = 0;
    if ($instructor_data) {
        $decoded_data = maybe_unserialize($instructor_data); // Decode serialized data
        if (is_array($decoded_data) && isset($decoded_data[0]['id'])) {
            $instructor_id = intval($decoded_data[0]['id']); // Extract instructor ID
        }
    }
    error_log("Instructor ID: " . print_r($instructor_id, true));

    // 🔥 Step 3: Get the Instructor's Email
    $instructor_email = $instructor_id ? get_the_author_meta('user_email', $instructor_id) : '';
    error_log("Instructor Email: " . print_r($instructor_email, true));

    // 🔥 Step 4: Set the Email Recipient
    $to = $instructor_email ? $instructor_email : 'admin@learninglynks.com'; // Default fallback email
    error_log("Email will be sent to: " . print_r($to, true));

    // Construct the email body
    $body = "<p><strong>Student Name:</strong> {$first_name} {$last_name}</p>";
    $body .= "<p><strong>Student Email:</strong> {$email}</p>";
    $body .= "<p><strong>Student's Question:</strong> {$message}</p>";
    $body .= "<p><strong>Their Course:</strong> {$course_name}</p>";
    $body .= "<p><strong>Their Lesson:</strong> {$lesson_name}</p>";
    $body .= "<p><strong>Quiz Question:</strong> {$question_text}</p>";
    $body .= "<p><strong>Student's Answer:</strong> {$selected_answer}</p>";

    // Send the email with error handling
    if (wp_mail($to, "Student Question from Quiz", $body, ['Content-Type: text/html; charset=UTF-8'])) {
        error_log("Email sent successfully to instructor.");
        wp_send_json_success(['message' => 'Email sent successfully!']);
    } else {
        error_log("Email sending failed.");
        wp_send_json_error(['message' => 'Failed to send email.']);
    }
}

add_action('wp_ajax_send_custom_email', 'wp_cep_send_email');
add_action('wp_ajax_nopriv_send_custom_email', 'wp_cep_send_email');
