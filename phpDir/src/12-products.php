<?php
// Lesson 12: Products Page
// Fetch products from Supabase and display them in a table

// SupabaseAuth must be initialized BEFORE any HTML output (session_start requirement)
require_once "auth/SupabaseAuth.php";
$auth = new SupabaseAuth();

include "functions.php";
include "includes/header.php";

// Fetch all products from Supabase
// Uses PostgREST query syntax: select=* gets all columns, order=name sorts alphabetically
try {
    $products = $auth->query('products', [
        'select' => '*,categories(name)',
        'order' => 'name.asc'
    ]);
    $error = null;
} catch (Exception $e) {
    $products = [];
    $error = $e->getMessage();
}
?>

<section class="content">

    <aside class="col-xs-4">
        <?php Navigation(); ?>
    </aside>

    <article class="main-content col-xs-8">
        <h1>Products</h1>

        <!-- Auth Status -->
        <?php if ($auth->isLoggedIn()): ?>
            <p style="color: green;">✅ Logged in as: <?php echo htmlspecialchars($auth->getCurrentUser()['email'] ?? 'Unknown'); ?></p>
        <?php else: ?>
            <p style="color: orange;">⚠️ Not logged in - <a href="11-authentication.php">Login here</a></p>
        <?php endif; ?>

        <?php if ($error): ?>
            <p style="color: red;">Error: <?php echo htmlspecialchars($error); ?></p>
        <?php elseif (!empty($products)): ?>
            <p><?php echo count($products); ?> products found</p>

            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f5f5f5;">
                        <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Name</th>
                        <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">SKU</th>
                        <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Category</th>
                        <th style="padding: 10px; border: 1px solid #ddd; text-align: right;">Price</th>
                        <th style="padding: 10px; border: 1px solid #ddd; text-align: right;">Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td style="padding: 10px; border: 1px solid #ddd;">
                                <?php echo htmlspecialchars($product["name"]); ?>
                            </td>
                            <td style="padding: 10px; border: 1px solid #ddd;">
                                <?php echo htmlspecialchars($product["sku"] ?? '-'); ?>
                            </td>
                            <td style="padding: 10px; border: 1px solid #ddd;">
                                <?php echo htmlspecialchars($product["categories"]["name"] ?? '-'); ?>
                            </td>
                            <td style="padding: 10px; border: 1px solid #ddd; text-align: right;">
                                €<?php echo number_format($product["price"], 2); ?>
                            </td>
                            <td style="padding: 10px; border: 1px solid #ddd; text-align: right;">
                                <?php
                                $stock = $product["stock_quantity"] ?? 0;
                                $color = $stock <= 10 ? 'red' : 'green';
                                echo "<span style='color: $color;'>$stock</span>";
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No products found.</p>
        <?php endif; ?>

        <!-- Debug Logs -->
        <?php echo $auth->renderLogs(); ?>
    </article>

</section>

<?php include "includes/footer.php"; ?>