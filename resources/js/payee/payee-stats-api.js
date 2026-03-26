export const getPayeeCategoryStats = async (payeeId, transactionType = null) => {
  const response = await window.axios.get(
    window.route('api.v1.payees.category-stats', {
      accountEntity: payeeId,
    }),
    {
      params: {
        transaction_type: transactionType,
      },
    },
  );

  return response.data;
};
