<!-- Subject: Clase cancelada por el alumno -->
<h1>¡Hola <?= esc_html( $teacher_name ); ?>!</h1>
<p>El alumno <strong><?= esc_html( $student_name ); ?></strong> ha cancelado la clase programada para el <strong><?= esc_html( $date ); ?></strong> a las <strong><?= esc_html( $time ); ?></strong>.</p>
<p>Puedes consultar tus reservas en tu panel de profesor.</p>
