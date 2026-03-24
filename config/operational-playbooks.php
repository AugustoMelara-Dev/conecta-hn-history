<?php

declare(strict_types=1);

use App\Domain\Access\Enums\Role;

return [
    'triggers' => [
        'lead_follow_up' => [
            'queue' => 'leads',
            'priority' => 'high',
            'sla_minutes' => 30,
            'route_key' => 'leads',
            'title' => 'Responder lead nuevo',
            'summary' => 'Contacta a {customer_name} y define el siguiente paso comercial antes de que el interes se enfrie.',
            'fallback_roles' => [Role::OPERATOR->value, Role::MANAGER->value, Role::ADMIN->value],
            'steps' => [
                'Responder en la primera media hora.',
                'Calificar necesidad y presupuesto.',
                'Mover a cita, cotizacion o siguiente mensaje concreto.',
            ],
        ],
        'appointment_booking' => [
            'queue' => 'appointments',
            'priority' => 'high',
            'sla_minutes' => 20,
            'route_key' => 'appointments',
            'title' => 'Confirmar cita nueva',
            'summary' => 'Asegura que {customer_name} tenga hora, profesional y contexto claro antes de la atencion.',
            'fallback_roles' => [Role::OPERATOR->value, Role::MANAGER->value],
            'steps' => [
                'Confirmar fecha, hora y servicio.',
                'Verificar datos de contacto y notas.',
                'Preparar staff, espacio o materiales necesarios.',
            ],
        ],
        'appointment_reminder' => [
            'queue' => 'appointments',
            'priority' => 'normal',
            'sla_minutes' => 60,
            'route_key' => 'appointments',
            'title' => 'Supervisar recordatorio de cita',
            'summary' => 'Verifica asistencia probable y prepara la operacion para {customer_name}.',
            'fallback_roles' => [Role::OPERATOR->value, Role::MANAGER->value],
            'steps' => [
                'Revisar que el recordatorio salio bien.',
                'Confirmar asistencia o detectar riesgo de no-show.',
                'Reordenar agenda si hace falta.',
            ],
        ],
        'cart_recovery' => [
            'queue' => 'retention',
            'priority' => 'high',
            'sla_minutes' => 120,
            'route_key' => 'marketing',
            'title' => 'Recuperar carrito abandonado',
            'summary' => 'Activa seguimiento comercial sobre {customer_name} y protege una venta que ya mostro intencion.',
            'fallback_roles' => [Role::OPERATOR->value, Role::ADMIN->value],
            'steps' => [
                'Revisar monto e interes del carrito.',
                'Enviar seguimiento por WhatsApp o cupon.',
                'Cerrar la venta o clasificar la oportunidad.',
            ],
        ],
        'repeat_purchase' => [
            'queue' => 'retention',
            'priority' => 'normal',
            'sla_minutes' => 1440,
            'route_key' => 'marketing',
            'title' => 'Activar oportunidad de recompra',
            'summary' => 'Convierte la entrega de {order_number} en una oportunidad de ticket, recompra o fidelizacion.',
            'fallback_roles' => [Role::MANAGER->value, Role::ADMIN->value],
            'steps' => [
                'Revisar el producto o servicio ya entregado.',
                'Proponer siguiente compra, mantenimiento o bundle.',
                'Mover el cliente a una campana o seguimiento concreto.',
            ],
        ],
        'renewal_attention' => [
            'queue' => 'billing',
            'priority' => 'critical',
            'sla_minutes' => 1440,
            'route_key' => 'billing',
            'title' => 'Atender renovacion PRO',
            'summary' => 'La cuenta vence en {days_until_expiry} dias. Confirma continuidad, cobro y riesgo comercial.',
            'fallback_roles' => [Role::MANAGER->value, Role::ADMIN->value],
            'steps' => [
                'Confirmar fecha de vencimiento y estado del plan.',
                'Contactar a la persona responsable de pago o decision.',
                'Dejar siguiente paso comercial antes de vencer.',
            ],
        ],
        'activation_follow_up' => [
            'queue' => 'activation',
            'priority' => 'high',
            'sla_minutes' => 1440,
            'route_key' => 'dashboard',
            'title' => 'Empujar activacion comercial',
            'summary' => '{tenant_name} necesita terminar de activarse. Atiende {activation_metric} y deja un siguiente paso concreto hoy.',
            'fallback_roles' => [Role::MANAGER->value, Role::ADMIN->value],
            'steps' => [
                'Revisar si la cuenta ya publico oferta, canal de cierre y datos de confianza.',
                'Contactar al negocio con un siguiente paso concreto para completar onboarding.',
                'Mover la cuenta de activacion a operacion real sin dejar frentes abiertos.',
            ],
        ],
        'expansion_opportunity' => [
            'queue' => 'growth',
            'priority' => 'high',
            'sla_minutes' => 2880,
            'route_key' => 'dashboard',
            'title' => 'Abrir expansion comercial',
            'summary' => '{tenant_name} ya tiene base para crecer en {segment_label}. Atiende {expansion_metric} y mueve el siguiente upsell.',
            'fallback_roles' => [Role::MANAGER->value, Role::ADMIN->value],
            'steps' => [
                'Validar que la cuenta ya tiene oferta, canal de cierre y operacion minima estable.',
                'Proponer upgrade a PRO, bundle, modulo o expansion del uso actual.',
                'Dejar owner, siguiente toque y oferta concreta dentro del CRM.',
            ],
        ],
        'tenant_churn_watch' => [
            'queue' => 'retention',
            'priority' => 'critical',
            'sla_minutes' => 720,
            'route_key' => 'dashboard',
            'title' => 'Proteger cuenta en riesgo',
            'summary' => '{tenant_name} muestra senales de churn. Atiende {watch_metric} y deja siguiente paso comercial hoy.',
            'fallback_roles' => [Role::MANAGER->value, Role::ADMIN->value],
            'steps' => [
                'Revisar la salud de la cuenta, oferta publicada y actividad reciente.',
                'Contactar a la persona responsable del negocio antes de que la cuenta se enfrie.',
                'Definir owner, siguiente toque y plan de recuperacion dentro del panel.',
            ],
        ],
    ],
    'segments' => [
        'dental_clinic' => [
            'activation_follow_up' => [
                'title' => 'Completar activacion de clinica dental',
                'summary' => '{tenant_name} necesita cerrar valoracion, agenda y recepcion para empezar a convertir pacientes con menos friccion.',
                'preferred_presets' => ['patient_success', 'dental_reception', 'clinical_coordinator'],
                'steps' => [
                    'Publicar valoraciones y tratamientos clave para bajar ansiedad inicial.',
                    'Confirmar recepcion, agenda y capacidad de atencion visible.',
                    'Dejar siguiente toque comercial hasta que la clinica opere con ritmo.',
                ],
            ],
            'expansion_opportunity' => [
                'title' => 'Abrir continuidad de tratamientos dentales',
                'summary' => '{tenant_name} ya puede crecer con recordatorios, continuidad de tratamientos y una operacion dental mas completa.',
                'preferred_presets' => ['patient_success', 'clinical_coordinator', 'dental_reception'],
                'steps' => [
                    'Proponer continuidad de tratamientos, controles y recordatorios de paciente.',
                    'Abrir upgrade a PRO o flujos que mejoren agenda, seguimiento y reputacion.',
                    'Dejar owner, propuesta y fecha de cierre dentro del CRM.',
                ],
            ],
            'lead_follow_up' => [
                'title' => 'Dar seguimiento a valoracion dental',
                'summary' => 'Convierte la consulta de {customer_name} en valoracion, plan o siguiente paso clinico.',
                'preferred_presets' => ['dental_reception', 'clinical_coordinator', 'patient_success'],
            ],
            'appointment_booking' => [
                'title' => 'Confirmar cita clinica nueva',
                'preferred_presets' => ['dental_reception', 'clinical_coordinator'],
            ],
            'appointment_reminder' => [
                'preferred_presets' => ['dental_reception', 'clinical_coordinator'],
            ],
            'repeat_purchase' => [
                'title' => 'Activar continuidad de tratamiento',
                'preferred_presets' => ['patient_success', 'clinical_coordinator'],
            ],
            'renewal_attention' => [
                'preferred_presets' => ['patient_success', 'clinical_coordinator'],
            ],
        ],
        'corporate_law_firm' => [
            'activation_follow_up' => [
                'title' => 'Completar activacion de intake legal',
                'summary' => '{tenant_name} necesita cerrar areas de practica, discovery y canal de seguimiento para convertir empresas con mas seriedad.',
                'preferred_presets' => ['legal_intake', 'legal_accounts', 'legal_growth'],
                'steps' => [
                    'Aterrizar areas de practica y discovery legal en el sitio y el panel.',
                    'Abrir un primer flujo de intake con criterios de empresa y seguimiento serio.',
                    'Asignar owner y siguiente toque hasta que la firma quede operando en ritmo comercial.',
                ],
            ],
            'expansion_opportunity' => [
                'title' => 'Abrir upsell legal corporativo',
                'summary' => '{tenant_name} ya puede crecer con discovery legal, seguimiento de retainer y una propuesta B2B mas clara.',
                'preferred_presets' => ['legal_growth', 'legal_accounts', 'legal_intake'],
                'steps' => [
                    'Abrir propuesta de retainer, compliance o paquete corporativo.',
                    'Empujar la cuenta hacia una operacion comercial mas consultiva y recurrente.',
                    'Dejar siguiente oferta, owner y fecha objetivo dentro del CRM.',
                ],
            ],
            'lead_follow_up' => [
                'title' => 'Mover lead corporativo a discovery',
                'preferred_presets' => ['legal_intake', 'legal_accounts', 'legal_growth'],
            ],
            'appointment_booking' => [
                'title' => 'Preparar discovery legal',
                'preferred_presets' => ['legal_intake', 'legal_accounts'],
            ],
            'repeat_purchase' => [
                'title' => 'Activar seguimiento post-reunion',
                'preferred_presets' => ['legal_growth', 'legal_accounts'],
            ],
            'renewal_attention' => [
                'preferred_presets' => ['legal_accounts', 'legal_growth'],
            ],
        ],
        'financial_consultancy' => [
            'activation_follow_up' => [
                'title' => 'Completar activacion consultiva financiera',
                'summary' => '{tenant_name} necesita cerrar diagnostico, paquetes y canal de seguimiento para que el discovery no se enfrie.',
                'preferred_presets' => ['finance_discovery', 'finance_delivery', 'finance_growth'],
                'steps' => [
                    'Aterrizar diagnostico, paquetes y problemas financieros visibles en la oferta.',
                    'Abrir agenda y canal de cierre para discovery ejecutivo.',
                    'Sostener seguimiento hasta que la consultoria ya opere con propuesta real.',
                ],
            ],
            'expansion_opportunity' => [
                'title' => 'Abrir expansion de consultoria financiera',
                'summary' => '{tenant_name} ya puede crecer con diagnosticos recurrentes, fractional CFO y seguimiento financiero de mayor ticket.',
                'preferred_presets' => ['finance_growth', 'finance_delivery', 'finance_discovery'],
                'steps' => [
                    'Empujar upsell hacia acompanamiento recurrente, tableros o CFO as a service.',
                    'Mover la cuenta a un plan PRO o una capa comercial mas consultiva.',
                    'Registrar owner, siguiente toque y propuesta economica clara.',
                ],
            ],
            'lead_follow_up' => [
                'title' => 'Mover lead financiero a diagnostico',
                'preferred_presets' => ['finance_discovery', 'finance_delivery', 'finance_growth'],
            ],
            'appointment_booking' => [
                'preferred_presets' => ['finance_discovery', 'finance_delivery'],
            ],
            'repeat_purchase' => [
                'title' => 'Activar siguiente reunion o propuesta',
                'preferred_presets' => ['finance_growth', 'finance_delivery'],
            ],
            'renewal_attention' => [
                'preferred_presets' => ['finance_delivery', 'finance_growth'],
            ],
        ],
        'premium_phone_store' => [
            'activation_follow_up' => [
                'title' => 'Completar activacion de retail premium',
                'summary' => '{tenant_name} necesita cerrar flagships, bundles y canal de cierre para vender ticket alto con mas confianza.',
                'preferred_presets' => ['phone_sales_advisor', 'premium_aftercare', 'device_inventory_lead'],
                'steps' => [
                    'Publicar equipos insignia, bundles y mensajes de respaldo postventa.',
                    'Asegurar WhatsApp, cobertura y flujo de cotizacion premium.',
                    'Dejar siguiente toque hasta que la tienda opere como vitrina de alto valor.',
                ],
            ],
            'expansion_opportunity' => [
                'title' => 'Abrir expansion flagship y bundles',
                'summary' => '{tenant_name} ya puede crecer con upsell de accesorios, trade-in, aftercare y una operacion premium mas completa.',
                'preferred_presets' => ['premium_aftercare', 'phone_sales_advisor', 'device_inventory_lead'],
                'steps' => [
                    'Empujar bundles, upgrades y soporte premium sobre trafico ya listo para cerrar.',
                    'Abrir propuesta de plan PRO, marketing o expansion de catalogo premium.',
                    'Dejar owner, playbook y proxima oferta dentro del CRM.',
                ],
            ],
            'lead_follow_up' => [
                'title' => 'Responder cotizacion premium',
                'preferred_presets' => ['phone_sales_advisor', 'device_inventory_lead', 'premium_aftercare'],
            ],
            'cart_recovery' => [
                'preferred_presets' => ['phone_sales_advisor', 'premium_aftercare'],
            ],
            'repeat_purchase' => [
                'title' => 'Activar upgrade o bundle premium',
                'preferred_presets' => ['premium_aftercare', 'phone_sales_advisor'],
            ],
            'renewal_attention' => [
                'preferred_presets' => ['premium_aftercare', 'device_inventory_lead'],
            ],
        ],
        'hardware_store' => [
            'activation_follow_up' => [
                'title' => 'Completar activacion de ferreteria',
                'summary' => '{tenant_name} necesita cerrar materiales visibles, cotizacion rapida y cobertura para mover ventas por proyecto.',
                'preferred_presets' => ['hardware_counter', 'project_sales', 'hardware_inventory'],
                'steps' => [
                    'Publicar herramientas, materiales y colecciones orientadas a proyecto.',
                    'Asegurar canal de cotizacion rapida y claridad de entrega o pickup.',
                    'Sostener seguimiento hasta que la ferreteria ya tenga flujo comercial activo.',
                ],
            ],
            'expansion_opportunity' => [
                'title' => 'Abrir crecimiento por proyectos y cobertura',
                'summary' => '{tenant_name} ya puede crecer con cuentas por proyecto, cobertura extendida y una cotizacion mas estructurada.',
                'preferred_presets' => ['project_sales', 'hardware_counter', 'hardware_inventory'],
                'steps' => [
                    'Empujar proyectos recurrentes, cotizaciones medianas y cobertura de entrega.',
                    'Abrir upgrade a PRO o modulos que mejoren seguimiento y cierre de obra.',
                    'Dejar owner, oportunidad y fecha de avance dentro del CRM.',
                ],
            ],
            'lead_follow_up' => [
                'title' => 'Responder cotizacion de ferreteria',
                'preferred_presets' => ['hardware_counter', 'project_sales', 'hardware_inventory'],
            ],
            'cart_recovery' => [
                'preferred_presets' => ['hardware_counter', 'project_sales'],
            ],
            'repeat_purchase' => [
                'title' => 'Activar recompra por proyecto',
                'preferred_presets' => ['project_sales', 'hardware_counter'],
            ],
            'renewal_attention' => [
                'preferred_presets' => ['project_sales', 'hardware_inventory'],
            ],
        ],
        'premium_barbershop' => [
            'lead_follow_up' => [
                'title' => 'Responder consulta premium',
                'preferred_presets' => ['barber_concierge', 'grooming_lead', 'membership_retention'],
            ],
            'appointment_booking' => [
                'preferred_presets' => ['barber_concierge', 'grooming_lead'],
            ],
            'appointment_reminder' => [
                'preferred_presets' => ['barber_concierge', 'grooming_lead'],
            ],
            'repeat_purchase' => [
                'title' => 'Empujar siguiente visita premium',
                'preferred_presets' => ['membership_retention', 'barber_concierge'],
            ],
            'renewal_attention' => [
                'preferred_presets' => ['membership_retention', 'grooming_lead'],
            ],
        ],
        'spa_studio' => [
            'lead_follow_up' => [
                'title' => 'Responder consulta de bienestar',
                'preferred_presets' => ['spa_concierge', 'therapy_operations', 'wellness_retention'],
            ],
            'appointment_booking' => [
                'preferred_presets' => ['spa_concierge', 'therapy_operations'],
            ],
            'appointment_reminder' => [
                'preferred_presets' => ['spa_concierge', 'therapy_operations'],
            ],
            'repeat_purchase' => [
                'title' => 'Activar continuidad de tratamiento o paquete',
                'preferred_presets' => ['wellness_retention', 'spa_concierge'],
            ],
            'renewal_attention' => [
                'preferred_presets' => ['wellness_retention', 'therapy_operations'],
            ],
        ],
        'auto_parts_store' => [
            'lead_follow_up' => [
                'title' => 'Responder consulta tecnica de repuesto',
                'preferred_presets' => ['parts_advisor', 'parts_coordinator', 'workshop_followup'],
            ],
            'cart_recovery' => [
                'preferred_presets' => ['parts_advisor', 'workshop_followup'],
            ],
            'repeat_purchase' => [
                'title' => 'Activar mantenimiento o recompra de repuesto',
                'preferred_presets' => ['workshop_followup', 'parts_coordinator'],
            ],
            'renewal_attention' => [
                'preferred_presets' => ['workshop_followup', 'parts_coordinator'],
            ],
        ],
    ],
];
